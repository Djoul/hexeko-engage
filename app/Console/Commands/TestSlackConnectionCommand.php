<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SlackService;
use Exception;
use Illuminate\Console\Command;

class TestSlackConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the Slack connection and configuration';

    /**
     * Execute the console command.
     */
    public function handle(SlackService $slackService): int
    {
        $this->info('🔍 Testing Slack connection...');
        $this->newLine();

        // Display current configuration
        $this->table(
            ['Configuration', 'Value'],
            [
                ['Token', substr(is_scalar(config('services.slack.notifications.bot_user_oauth_token', '')) ? (string) config('services.slack.notifications.bot_user_oauth_token', '') : '', 0, 20).'...'],
                ['Default Channel', config('services.slack.notifications.channel', 'Not configured')],
                ['Username', config('services.slack.notifications.username', 'Not set')],
                ['Icon Emoji', config('services.slack.notifications.icon_emoji', 'Not set')],
            ]
        );

        $this->newLine();

        // Test connection
        try {
            if ($slackService->testConnection()) {
                $this->info('✅ Connection successful!');

                // Try to get channels
                $this->newLine();
                $this->info('📋 Available channels:');

                $channels = $slackService->getChannels();

                if ($channels->isEmpty()) {
                    $this->warn('No channels found. Make sure the bot has the necessary permissions.');
                } else {
                    $channelData = $channels->take(10)->map(function (array $channel): array {
                        return [
                            $channel['id'],
                            $channel['name'],
                            $channel['is_private'] ? 'Private' : 'Public',
                        ];
                    })->toArray();

                    $this->table(['ID', 'Name', 'Type'], $channelData);

                    if ($channels->count() > 10) {
                        $this->line("... and {$channels->count()} more channels");
                    }
                }

                // Send test message
                $this->newLine();
                if ($this->confirm('Would you like to send a test message?')) {
                    $defaultChannel = config('services.slack.notifications.channel');
                    $testChannel = $this->ask('Enter channel (leave empty for default)', is_string($defaultChannel) ? $defaultChannel : null);

                    $result = $slackService->sendMessage(
                        '🧪 Test message from Laravel - '.now()->format('Y-m-d H:i:s'),
                        is_string($testChannel) && $testChannel !== '' ? $testChannel : null
                    );

                    $this->info('✅ Test message sent successfully!');
                    if (array_key_exists('channel', $result)) {
                        $channel = is_scalar($result['channel']) ? (string) $result['channel'] : 'unknown';
                        $this->line("Channel: {$channel}");
                    }
                }

                return Command::SUCCESS;
            }
            $this->error('❌ Connection failed!');
            $this->warn('Please check your Slack token configuration.');

            return Command::FAILURE;
        } catch (Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->line('1. Check that SLACK_BOT_USER_OAUTH_TOKEN is set in .env');
            $this->line('2. Ensure the token starts with xoxb-');
            $this->line('3. Verify the bot has been invited to the channel');
            $this->line('4. Check that the bot has the necessary scopes (chat:write, files:write)');

            return Command::FAILURE;
        }
    }
}
