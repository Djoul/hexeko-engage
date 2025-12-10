<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Notifications\Messages\SlackMessage;
use App\Notifications\SlackMessageWithAttachment;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('slack')]
#[Group('notification')]
class SlackMessageWithAttachmentTest extends TestCase
{
    private string $testToken = 'xoxb-test-token';

    private string $testChannel = '#test-channel';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.slack.notifications.bot_user_oauth_token', $this->testToken);
        Config::set('services.slack.notifications.channel', $this->testChannel);
    }

    #[Test]
    public function it_sends_notification_via_slack_channel(): void
    {
        $notification = new SlackMessageWithAttachment('Test message');

        $channels = $notification->via(null);

        $this->assertEquals(['slack'], $channels);
    }

    #[Test]
    public function it_creates_slack_message_without_attachment(): void
    {
        $notification = new SlackMessageWithAttachment('Simple message');

        $slackMessage = $notification->toSlack(null);

        $this->assertInstanceOf(SlackMessage::class, $slackMessage);
        $this->assertEquals('Simple message', $slackMessage->content);
        $this->assertEquals($this->testChannel, $slackMessage->channel);
    }

    #[Test]
    public function it_creates_slack_message_with_custom_title(): void
    {
        $testFile = sys_get_temp_dir().'/test-notification.pdf';
        file_put_contents($testFile, 'PDF content');

        $notification = new SlackMessageWithAttachment(
            'Document attached',
            $testFile,
            'document.pdf',
            'Monthly Report'
        );

        $this->assertEquals('Document attached', $notification->message);
        $this->assertEquals($testFile, $notification->filePath);
        $this->assertEquals('document.pdf', $notification->fileName);
        $this->assertEquals('Monthly Report', $notification->title);

        unlink($testFile);
    }

    #[Test]
    public function it_uploads_file_when_attachment_is_provided(): void
    {
        $testFile = sys_get_temp_dir().'/attachment.txt';
        file_put_contents($testFile, 'File content');

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => true,
                'file' => [
                    'id' => 'F123456',
                    'name' => 'attachment.txt',
                ],
            ], 200),
        ]);

        $notification = new SlackMessageWithAttachment(
            'Message with file',
            $testFile,
            'attachment.txt',
            'Test Attachment'
        );

        $slackMessage = $notification->toSlack(null);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/files.upload') &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->testToken);
        });

        $this->assertInstanceOf(SlackMessage::class, $slackMessage);

        unlink($testFile);
    }

    #[Test]
    public function it_uses_basename_as_filename_when_not_provided(): void
    {
        $testFile = sys_get_temp_dir().'/auto-name.csv';
        file_put_contents($testFile, 'CSV data');

        $notification = new SlackMessageWithAttachment(
            'CSV file',
            $testFile
        );

        $this->assertEquals('auto-name.csv', $notification->fileName);

        unlink($testFile);
    }

    #[Test]
    public function it_logs_error_when_slack_upload_fails(): void
    {
        $testFile = sys_get_temp_dir().'/error-file.txt';
        file_put_contents($testFile, 'Content');

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => false,
                'error' => 'file_upload_error',
            ], 200),
        ]);

        Log::shouldReceive('error')
            ->times(3)
            ->withArgs(function ($message, array $context): bool {
                return in_array($message, ['Erreur upload Slack', 'Slack API error', 'Exception during Slack file upload', 'Failed to upload file to Slack'], true) &&
                       (isset($context['response']) || isset($context['error']) || isset($context['file']));
            })
            ->andReturnNull();

        $notification = new SlackMessageWithAttachment(
            'Failed upload',
            $testFile
        );

        $notification->toSlack(null);

        unlink($testFile);
    }

    #[Test]
    public function it_returns_upload_response_data(): void
    {
        $testFile = sys_get_temp_dir().'/response-test.txt';
        file_put_contents($testFile, 'Test');

        $expectedResponse = [
            'ok' => true,
            'file' => [
                'id' => 'F999999',
                'name' => 'response-test.txt',
                'permalink' => 'https://slack.com/files/F999999',
            ],
        ];

        Http::fake([
            'slack.com/api/files.upload' => Http::response($expectedResponse, 200),
        ]);

        $notification = new SlackMessageWithAttachment(
            'Test response',
            $testFile
        );

        // Access protected method via reflection for testing
        $reflection = new ReflectionClass($notification);
        $method = $reflection->getMethod('uploadFileToSlack');
        $method->setAccessible(true);

        $result = $method->invoke($notification);

        $this->assertEquals($expectedResponse, $result);

        unlink($testFile);
    }

    #[Test]
    public function it_implements_should_queue_interface(): void
    {
        $notification = new SlackMessageWithAttachment('Queue test');

        $this->assertContains(
            'Illuminate\Contracts\Queue\ShouldQueue',
            class_implements($notification)
        );
    }

    #[Test]
    public function it_uses_queueable_trait(): void
    {
        $notification = new SlackMessageWithAttachment('Queue test');

        $traits = class_uses($notification);
        $this->assertContains('Illuminate\Bus\Queueable', $traits);
    }

    #[Test]
    public function it_sends_notification_to_user_with_slack_route(): void
    {
        $notification = new SlackMessageWithAttachment('User notification');

        // Test that the notification routes to slack
        $via = $notification->via(null);
        $this->assertEquals(['slack'], $via);

        // Test that notification creates a SlackMessage
        $slackMessage = $notification->toSlack(null);
        $this->assertInstanceOf(SlackMessage::class, $slackMessage);
        $this->assertEquals('User notification', $slackMessage->content);
    }

    #[Test]
    public function it_handles_multiple_file_uploads_sequentially(): void
    {
        $file1 = sys_get_temp_dir().'/file1.txt';
        $file2 = sys_get_temp_dir().'/file2.txt';
        file_put_contents($file1, 'Content 1');
        file_put_contents($file2, 'Content 2');

        Http::fake([
            'slack.com/api/files.upload' => Http::sequence()
                ->push(['ok' => true, 'file' => ['id' => 'F1']], 200)
                ->push(['ok' => true, 'file' => ['id' => 'F2']], 200),
        ]);

        $notification1 = new SlackMessageWithAttachment('First file', $file1);
        $notification2 = new SlackMessageWithAttachment('Second file', $file2);

        $notification1->toSlack(null);
        $notification2->toSlack(null);

        Http::assertSentCount(2);

        unlink($file1);
        unlink($file2);
    }

    #[Test]
    public function it_constructs_with_null_file_path(): void
    {
        $notification = new SlackMessageWithAttachment('No attachment');

        $this->assertEquals('No attachment', $notification->message);
        $this->assertNull($notification->filePath);
        $this->assertNull($notification->fileName);
        $this->assertNull($notification->title);
    }

    #[Test]
    public function it_does_not_upload_when_file_path_is_null(): void
    {
        Http::fake();

        $notification = new SlackMessageWithAttachment('No file');
        $notification->toSlack(null);

        Http::assertNothingSent();
    }

    #[Test]
    public function it_handles_special_characters_in_filename(): void
    {
        $testFile = sys_get_temp_dir().'/spécial-çhars éà.txt';
        file_put_contents($testFile, 'Content');

        $notification = new SlackMessageWithAttachment(
            'Special chars test',
            $testFile
        );

        $this->assertEquals('spécial-çhars éà.txt', $notification->fileName);

        unlink($testFile);
    }
}
