<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slack')]
class TestSlackNotificationCommandTest extends TestCase
{
    #[Test]
    public function it_sends_message_to_specified_channel_successfully(): void
    {
        // Arrange - Mock successful response
        Http::fake([
            'slack.com/*' => Http::response([
                'ok' => true,
                'ts' => '1234567890.123456',
                'channel' => 'C1234567890',
            ], 200),
        ]);

        $channel = 'up-engage-tech';
        $message = 'Test message from command';

        // Act & Assert
        $this->artisan('slack:send', [
            'channel' => $channel,
            'message' => $message,
        ])
            ->expectsOutput('Sending test message to Slack...')
            ->expectsOutput("Channel: {$channel}")
            ->expectsOutput("Message: {$message}")
            ->expectsOutput('✅ Message sent successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_slack_api_errors_gracefully(): void
    {
        // Arrange - Mock failed response
        Http::fake([
            'slack.com/*' => Http::response([
                'ok' => false,
                'error' => 'invalid_auth',
            ], 401),
        ]);

        $channel = 'up-engage-tech';
        $message = 'Test message';

        // Act & Assert
        $this->artisan('slack:send', [
            'channel' => $channel,
            'message' => $message,
        ])
            ->expectsOutput('Sending test message to Slack...')
            ->expectsOutputToContain('❌ Failed to send message:')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_accepts_channel_without_hash_prefix(): void
    {
        // Arrange - Mock successful response
        Http::fake([
            'slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = 'general';
        $message = 'Test without hash';

        // Act & Assert
        $this->artisan('slack:send', [
            'channel' => $channel,
            'message' => $message,
        ])
            ->expectsOutput('✅ Message sent successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_accepts_channel_with_hash_prefix(): void
    {
        // Arrange - Mock successful response
        Http::fake([
            'slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = '#general';
        $message = 'Test with hash';

        // Act & Assert
        $this->artisan('slack:send', [
            'channel' => $channel,
            'message' => $message,
        ])
            ->expectsOutput('✅ Message sent successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_sends_message_with_multiline_content(): void
    {
        // Arrange - Mock successful response
        Http::fake([
            'slack.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = 'up-engage-tech';
        $message = "Line 1\nLine 2\nLine 3";

        // Act & Assert
        $this->artisan('slack:send', [
            'channel' => $channel,
            'message' => $message,
        ])
            ->expectsOutput('✅ Message sent successfully!')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_displays_response_details_when_details_flag_is_set(): void
    {
        // Arrange - Mock successful response
        Http::fake([
            'slack.com/*' => Http::response([
                'ok' => true,
                'channel' => 'C1234567890',
                'ts' => '1234567890.123456',
            ], 200),
        ]);

        $channel = 'up-engage-tech';
        $message = 'Details test';

        // Act & Assert
        $this->artisan('slack:send', [
            'channel' => $channel,
            'message' => $message,
            '--details' => true,
        ])
            ->expectsOutput('✅ Message sent successfully!')
            ->expectsOutputToContain('Response details:')
            ->assertExitCode(0);
    }
}
