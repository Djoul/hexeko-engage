<?php

namespace Tests\Unit\Services;

use App\Exceptions\SlackException;
use App\Services\SlackService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('slack')]
#[Group('notification')]
class SlackServiceTest extends TestCase
{
    private SlackService $slackService;

    private string $testToken = 'xoxb-test-token';

    private string $testChannel = '#test-channel';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.slack.notifications.bot_user_oauth_token', $this->testToken);
        Config::set('services.slack.notifications.channel', $this->testChannel);

        $this->slackService = new SlackService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_sends_a_simple_message_to_default_channel(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
                'ts' => '1234567890.123456',
                'message' => [
                    'text' => 'Test message',
                    'ts' => '1234567890.123456',
                ],
            ], 200),
        ]);

        $result = $this->slackService->sendMessage('Test message');

        $this->assertTrue($result['ok']);
        $this->assertEquals($this->testChannel, $result['channel']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://slack.com/api/chat.postMessage' &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->testToken) &&
                   $request['channel'] === $this->testChannel &&
                   $request['text'] === 'Test message';
        });
    }

    #[Test]
    public function it_sends_a_message_to_specific_channel(): void
    {
        $customChannel = '#custom-channel';

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $customChannel,
                'ts' => '1234567890.123456',
            ], 200),
        ]);

        $result = $this->slackService->sendMessage('Custom message', $customChannel);

        $this->assertTrue($result['ok']);
        $this->assertEquals($customChannel, $result['channel']);

        Http::assertSent(function (Request $request) use ($customChannel): bool {
            return $request['channel'] === $customChannel &&
                   $request['text'] === 'Custom message';
        });
    }

    #[Test]
    public function it_throws_exception_when_slack_api_returns_error(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
                'error' => 'channel_not_found',
            ], 200),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $message === 'Slack API error' &&
                       $context['error'] === 'channel_not_found' &&
                       isset($context['response']);
            })
            ->andReturnNull();

        $this->expectException(SlackException::class);
        $this->expectExceptionMessage('Slack API error: channel_not_found');

        $this->slackService->sendMessage('Test message');
    }

    #[Test]
    public function it_uploads_a_file_successfully(): void
    {
        $testFile = sys_get_temp_dir().'/test-file.txt';
        file_put_contents($testFile, 'Test content');

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => true,
                'file' => [
                    'id' => 'F1234567890',
                    'name' => 'test-file.txt',
                    'title' => 'Test File',
                ],
            ], 200),
        ]);

        $result = $this->slackService->uploadFile($testFile, 'File message', null, 'Test File');

        $this->assertTrue($result['ok']);
        $this->assertEquals('F1234567890', $result['file']['id']);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/files.upload') &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->testToken);
        });

        unlink($testFile);
    }

    #[Test]
    public function it_throws_exception_when_file_does_not_exist(): void
    {
        $nonExistentFile = '/path/to/non/existent/file.txt';

        $this->expectException(SlackException::class);
        $this->expectExceptionMessage("File does not exist: {$nonExistentFile}");

        $this->slackService->uploadFile($nonExistentFile);
    }

    #[Test]
    public function it_uploads_file_to_specific_channel(): void
    {
        $testFile = sys_get_temp_dir().'/test-upload.txt';
        file_put_contents($testFile, 'Upload content');
        $customChannel = '#uploads';

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => true,
                'file' => ['id' => 'F987654321'],
            ], 200),
        ]);

        $result = $this->slackService->uploadFile($testFile, null, $customChannel);

        $this->assertTrue($result['ok']);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/files.upload');
        });

        unlink($testFile);
    }

    #[Test]
    public function it_sends_message_with_file_attachment(): void
    {
        $testFile = sys_get_temp_dir().'/attachment.pdf';
        file_put_contents($testFile, 'PDF content');

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => true,
                'file' => [
                    'id' => 'F111111111',
                    'name' => 'attachment.pdf',
                ],
            ], 200),
        ]);

        $result = $this->slackService->sendMessageWithFile(
            'Here is the document',
            $testFile,
            null,
            'Important Document'
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/files.upload');
        });

        unlink($testFile);
    }

    #[Test]
    public function it_sends_rich_message_with_blocks(): void
    {
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Bold text* and _italic text_',
                ],
            ],
            [
                'type' => 'divider',
            ],
            [
                'type' => 'section',
                'fields' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => '*Field 1:*\nValue 1',
                    ],
                    [
                        'type' => 'mrkdwn',
                        'text' => '*Field 2:*\nValue 2',
                    ],
                ],
            ],
        ];

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
                'ts' => '1234567890.123456',
            ], 200),
        ]);

        $result = $this->slackService->sendRichMessage($blocks);

        $this->assertTrue($result['ok']);

        Http::assertSent(function (Request $request) use ($blocks): bool {
            return $request['blocks'] === $blocks &&
                   $request['channel'] === $this->testChannel;
        });
    }

    #[Test]
    public function it_sends_rich_message_to_specific_channel(): void
    {
        $customChannel = '#announcements';
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => 'Important Announcement',
                ],
            ],
        ];

        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $customChannel,
            ], 200),
        ]);

        $result = $this->slackService->sendRichMessage($blocks, $customChannel);

        $this->assertTrue($result['ok']);

        Http::assertSent(function (Request $request) use ($customChannel, $blocks): bool {
            return $request['channel'] === $customChannel &&
                   $request['blocks'] === $blocks;
        });
    }

    #[Test]
    public function it_handles_slack_api_error_for_file_upload(): void
    {
        $testFile = sys_get_temp_dir().'/error-test.txt';
        file_put_contents($testFile, 'Content');

        Http::fake([
            'slack.com/api/files.upload' => Http::response([
                'ok' => false,
                'error' => 'not_authed',
            ], 200),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $message === 'Slack API error' &&
                       $context['error'] === 'not_authed' &&
                       isset($context['response']);
            })
            ->andReturnNull();

        $this->expectException(SlackException::class);
        $this->expectExceptionMessage('Slack API error: not_authed');

        $this->slackService->uploadFile($testFile);

        unlink($testFile);
    }

    #[Test]
    public function it_handles_slack_api_error_for_rich_message(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
                'error' => 'invalid_blocks',
            ], 200),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $message === 'Slack API error' &&
                       $context['error'] === 'invalid_blocks' &&
                       isset($context['response']);
            })
            ->andReturnNull();

        $this->expectException(SlackException::class);
        $this->expectExceptionMessage('Slack API error: invalid_blocks');

        $this->slackService->sendRichMessage([]);
    }

    #[Test]
    public function it_uses_default_channel_when_not_specified(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'channel' => $this->testChannel,
            ], 200),
        ]);

        $this->slackService->sendMessage('Default channel test');

        Http::assertSent(function (Request $request): bool {
            return $request['channel'] === $this->testChannel;
        });
    }

    #[Test]
    public function it_handles_undefined_error_gracefully(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response([
                'ok' => false,
            ], 200),
        ]);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, array $context): bool {
                return $message === 'Slack API error' &&
                       $context['error'] === 'Unknown error' &&
                       isset($context['response']);
            })
            ->andReturnNull();

        $this->expectException(SlackException::class);
        $this->expectExceptionMessage('Slack API error: Unknown error');

        $this->slackService->sendMessage('Test');
    }
}
