<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SlackService;
use Exception;
use Illuminate\Console\Command;

class SendSlackMessageCommand extends Command
{
    protected $signature = 'slack:send
                            {channel : The Slack channel (e.g., #general or up-engage-tech)}
                            {message : The message to send}
                            {--details : Display detailed response information}';

    protected $description = 'Send a message to a Slack channel';

    public function __construct(
        private readonly SlackService $slackService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $message = $this->argument('message');
        $channel = $this->argument('channel') ?? config('services.slack.notifications.channel', '#general');

        $this->info('Sending test message to Slack...');
        $this->line("Channel: {$channel}");
        $this->line("Message: {$message}");
        $this->newLine();

        try {
            $response = $this->slackService->sendToPublicChannel(
                (string) $message,
                (string) $channel
            );

            $this->displaySuccess($response);

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->displayError($e);

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function displaySuccess(array $response): void
    {
        $this->info('✅ Message sent successfully!');

        if ($this->option('details')) {
            $this->newLine();
            $this->line('Response details:');
            $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    private function displayError(Exception $e): void
    {
        $this->error("❌ Failed to send message: {$e->getMessage()}");

        if ($this->option('details')) {
            $this->newLine();
            $this->line('Error details:');
            $this->line($e->getTraceAsString());
        }
    }
}
