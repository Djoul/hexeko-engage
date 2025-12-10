<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Notifications\SlackMessageWithAttachment;
use App\Services\SlackService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('slack')]
#[Group('notification')]
class SlackNotificationIntegrationTest extends TestCase
{
    private string $testToken = 'xoxb-test-integration-token';

    private string $testChannel = '#test-integration';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.slack.notifications.bot_user_oauth_token', $this->testToken);
        Config::set('services.slack.notifications.channel', $this->testChannel);
        Config::set('services.slack.notifications.username', 'TestBot');
        Config::set('services.slack.notifications.icon_emoji', ':test:');
    }

    #[Test]
    public function it_sends_simple_notification_through_service(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
                'ts' => '1234567890.123456',
                'message' => ['text' => 'Integration test'],
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->sendMessage('Integration test');

        $this->assertTrue($result['ok']);
        $this->assertEquals($this->testChannel, $result['channel']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'chat.postMessage') &&
                   $request->data()['text'] === 'Integration test';
        });
    }

    #[Test]
    public function it_sends_notification_to_specific_channel(): void
    {
        $alertChannel = '#alerts';

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $alertChannel,
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->sendToPublicChannel('Alert message', 'alerts');

        $this->assertTrue($result['ok']);
        $this->assertEquals($alertChannel, $result['channel']);
    }

    #[Test]
    public function it_sends_notification_with_file_attachment(): void
    {
        $testFile = sys_get_temp_dir().'/integration-test.csv';
        file_put_contents($testFile, "name,value\ntest,123");

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => true,
                'file' => [
                    'id' => 'F_INTEGRATION_123',
                    'name' => 'integration-test.csv',
                    'permalink' => 'https://slack.com/files/F_INTEGRATION_123',
                ],
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->uploadFile(
            $testFile,
            'CSV Report attached',
            null,
            'Daily Report'
        );

        $this->assertTrue($result['ok']);
        $this->assertEquals('F_INTEGRATION_123', $result['file']['id']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/files.upload');
        });

        unlink($testFile);
    }

    #[Test]
    public function it_sends_rich_message_with_blocks(): void
    {
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => 'System Status Update',
                ],
            ],
            [
                'type' => 'section',
                'fields' => [
                    ['type' => 'mrkdwn', 'text' => '*Status:*\n✅ Operational'],
                    ['type' => 'mrkdwn', 'text' => '*Uptime:*\n99.9%'],
                ],
            ],
        ];

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->sendRichMessage($blocks, null, 'Status Update');

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) use ($blocks): bool {
            return $request->data()['blocks'] === $blocks;
        });
    }

    #[Test]
    public function it_sends_notification_with_user_mentions(): void
    {
        $userIds = ['U12345', 'U67890'];

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->sendWithMentions(
            'Please review this PR',
            $userIds
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->data()['text'], '<@U12345>') &&
                   str_contains($request->data()['text'], '<@U67890>') &&
                   str_contains($request->data()['text'], 'Please review this PR');
        });
    }

    #[Test]
    public function it_sends_notification_to_thread(): void
    {
        $threadTs = '1234567890.123456';

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
                'thread_ts' => $threadTs,
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->sendToThread(
            'Reply in thread',
            $threadTs
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) use ($threadTs): bool {
            return $request->data()['thread_ts'] === $threadTs &&
                   $request->data()['text'] === 'Reply in thread';
        });
    }

    #[Test]
    public function it_notifies_user_with_slack_notification(): void
    {
        Notification::fake();

        $user = ModelFactory::createUser([
            'email' => 'slack.test.'.uniqid().'@example.com',
        ]);

        $notification = SlackMessageWithAttachment::text(
            'Test notification for user'
        );

        $user->notify($notification);

        Notification::assertSentTo(
            [$user],
            SlackMessageWithAttachment::class,
            function ($notification): bool {
                return $notification->message === 'Test notification for user';
            }
        );
    }

    #[Test]
    public function it_handles_multiple_notifications_in_sequence(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::sequence()
                ->push(['ok' => true, 'channel' => '#alerts'], 200)
                ->push(['ok' => true, 'channel' => '#errors'], 200)
                ->push(['ok' => true, 'channel' => '#monitoring'], 200),
        ]);

        $service = app(SlackService::class);

        Config::set('services.slack.channels.alerts', '#alerts');
        Config::set('services.slack.channels.errors', '#errors');
        Config::set('services.slack.channels.monitoring', '#monitoring');

        // Send to different channels
        $result1 = $service->sendMessage('Alert', '#alerts');
        $result2 = $service->sendMessage('Error', '#errors');
        $result3 = $service->sendMessage('Metric', '#monitoring');

        $this->assertTrue($result1['ok']);
        $this->assertTrue($result2['ok']);
        $this->assertTrue($result3['ok']);

        Http::assertSentCount(3);
    }

    #[Test]
    public function it_tests_slack_connection(): void
    {
        Http::fake([
            'slack.com/api/auth.test' => Http::response([
                'ok' => true,
                'url' => 'https://test.slack.com/',
                'team' => 'Test Team',
                'user' => 'testbot',
            ], 200),
        ]);

        $service = app(SlackService::class);
        $isConnected = $service->testConnection();

        $this->assertTrue($isConnected);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'auth.test');
        });
    }

    #[Test]
    public function it_retrieves_list_of_channels(): void
    {
        Http::fake([
            'slack.com/api/conversations.list*' => Http::response([
                'ok' => true,
                'conversations' => [
                    ['id' => 'C123', 'name' => 'general', 'is_private' => false],
                    ['id' => 'C456', 'name' => 'random', 'is_private' => false],
                    ['id' => 'G789', 'name' => 'private-channel', 'is_private' => true],
                ],
            ], 200),
        ]);

        $service = app(SlackService::class);
        $channels = $service->getChannels();

        $this->assertCount(3, $channels);
        $this->assertEquals('general', $channels[0]['name']);
        $this->assertTrue($channels[2]['is_private']);
    }

    #[Test]
    public function it_sends_direct_message_to_user(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage*' => Http::response([
                'ok' => true,
                'channel' => 'D123456',
            ], 200),
        ]);

        $service = app(SlackService::class);
        $result = $service->sendDirectMessage(
            'Private message',
            'U123456'
        );

        $this->assertTrue($result['ok']);

        Http::assertSentCount(1);
    }

    #[Test]
    public function it_creates_notification_with_factory_methods(): void
    {
        // Test simple text notification
        $textNotification = SlackMessageWithAttachment::text('Simple message');
        $this->assertEquals('Simple message', $textNotification->message);
        $this->assertNull($textNotification->filePath);

        // Test notification with file
        $fileNotification = SlackMessageWithAttachment::withFile(
            'File attached',
            '/path/to/file.pdf',
            'Report'
        );
        $this->assertEquals('File attached', $fileNotification->message);
        $this->assertEquals('/path/to/file.pdf', $fileNotification->filePath);
        $this->assertEquals('Report', $fileNotification->title);

        // Test thread notification
        $threadNotification = SlackMessageWithAttachment::thread(
            'Thread reply',
            '123.456',
            '#general'
        );
        $this->assertEquals('Thread reply', $threadNotification->message);
        $this->assertEquals('123.456', $threadNotification->threadTs);
        $this->assertEquals('#general', $threadNotification->channel);

        // Test rich notification
        $blocks = [['type' => 'section', 'text' => ['type' => 'mrkdwn', 'text' => 'Rich']]];
        $richNotification = SlackMessageWithAttachment::rich(
            'Rich message',
            $blocks,
            '#announcements'
        );
        $this->assertEquals('Rich message', $richNotification->message);
        $this->assertEquals($blocks, $richNotification->blocks);
        $this->assertEquals('#announcements', $richNotification->channel);
    }
}
