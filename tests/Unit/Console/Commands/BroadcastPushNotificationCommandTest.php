<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Actions\Push\SendPushNotificationAction;
use App\DTOs\Push\PushNotificationDTO;
use App\Enums\NotificationTypes;
use App\Jobs\Push\SendPushNotificationJob;
use App\Models\PushNotification;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Exception\RuntimeException;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class BroadcastPushNotificationCommandTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_broadcasts_push_notification_with_required_parameters(): void
    {
        // Arrange
        Queue::fake();

        // Act
        $this->artisan('push:broadcast', [
            'title' => 'Test Notification',
            'message' => 'This is a test message',
        ])->assertSuccessful();

        // Assert
        Queue::assertPushed(SendPushNotificationJob::class, function ($job): bool {
            $notification = $job->notification;

            return $notification->title === 'Test Notification'
                && $notification->body === 'This is a test message'
                && $notification->delivery_type === 'broadcast'
                && $notification->type->is(NotificationTypes::SYSTEM);
        });
    }

    #[Test]
    public function it_broadcasts_push_notification_with_optional_data(): void
    {
        // Arrange
        Queue::fake();
        $data = [
            'action' => 'open_url',
            'target' => 'https://example.com',
        ];

        // Act
        $this->artisan('push:broadcast', [
            'title' => 'Notification with Data',
            'message' => 'Click to visit',
            '--data' => json_encode($data),
            '--icon' => 'https://example.com/icon.png',
            '--url' => 'https://example.com/promo',
        ])->assertSuccessful();

        // Assert
        Queue::assertPushed(SendPushNotificationJob::class, function ($job) use ($data): bool {
            $notification = $job->notification;

            return $notification->data === $data
                && $notification->icon === 'https://example.com/icon.png'
                && $notification->url === 'https://example.com/promo';
        });
    }

    #[Test]
    public function it_sends_notification_immediately_when_sync_flag_is_set(): void
    {
        // Arrange
        $action = $this->mock(SendPushNotificationAction::class);

        // Mock notification result
        $mockNotification = new PushNotification([
            'notification_id' => 'test-uuid',
            'title' => 'Sync Notification',
            'body' => 'Sent immediately',
            'status' => 'sent',
            'recipient_count' => 100,
            'external_id' => 'onesignal-id-123',
        ]);

        $action->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(function (PushNotificationDTO $dto): bool {
                return $dto->title === 'Sync Notification'
                    && $dto->body === 'Sent immediately';
            }))
            ->andReturn($mockNotification);

        // Act
        $this->artisan('push:broadcast', [
            'title' => 'Sync Notification',
            'message' => 'Sent immediately',
            '--sync' => true,
        ])
            ->expectsOutput('Broadcasting push notification...')
            ->expectsOutput('✓ Push notification sent to 100 recipients')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_segments_option(): void
    {
        // Arrange
        Queue::fake();
        $segments = ['premium', 'active'];

        // Act
        $this->artisan('push:broadcast', [
            'title' => 'Segmented Notification',
            'message' => 'For specific segments',
            '--segments' => implode(',', $segments),
        ])->assertSuccessful();

        // Assert
        Queue::assertPushed(SendPushNotificationJob::class, function ($job) use ($segments): bool {
            $notification = $job->notification;

            return $notification->delivery_type === 'segment'
                && isset($notification->data['segments'])
                && $notification->data['segments'] === $segments;
        });
    }

    #[Test]
    public function it_validates_json_data_format(): void
    {
        // Act & Assert
        $this->artisan('push:broadcast', [
            'title' => 'Test',
            'message' => 'Message',
            '--data' => 'invalid-json',
        ])
            ->expectsOutput('Invalid JSON format for data parameter')
            ->assertFailed();
    }

    #[Test]
    public function it_handles_error_when_sending_sync_notification(): void
    {
        // Arrange
        $action = $this->mock(SendPushNotificationAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->andThrow(new Exception('OneSignal API error'));

        // Act & Assert
        $this->artisan('push:broadcast', [
            'title' => 'Failed Notification',
            'message' => 'This will fail',
            '--sync' => true,
        ])
            ->expectsOutput('Broadcasting push notification...')
            ->expectsOutput('✗ Failed to send push notification: OneSignal API error')
            ->assertFailed();
    }

    #[Test]
    public function it_shows_help_information(): void
    {
        // Act & Assert
        $this->artisan('push:broadcast --help')
            ->expectsOutputToContain('Broadcast a push notification to all users or specific segments')
            ->expectsOutputToContain('title')
            ->expectsOutputToContain('message')
            ->expectsOutputToContain('--data')
            ->expectsOutputToContain('--segments')
            ->expectsOutputToContain('--sync')
            ->assertSuccessful();
    }

    #[Test]
    public function it_requires_title_parameter(): void
    {
        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "title, message")');

        $this->artisan('push:broadcast');
    }

    #[Test]
    public function it_requires_message_parameter(): void
    {
        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "message")');

        $this->artisan('push:broadcast', [
            'title' => 'Test Title',
        ]);
    }

    #[Test]
    public function it_handles_dry_run_mode(): void
    {
        // Arrange
        Queue::fake();

        // Act
        $this->artisan('push:broadcast', [
            'title' => 'Dry Run Test',
            'message' => 'This is a dry run',
            '--dry-run' => true,
        ])
            ->expectsOutput('🔍 DRY RUN MODE - No notification will be sent')
            ->expectsOutputToContain('Title: Dry Run Test')
            ->expectsOutputToContain('Message: This is a dry run')
            ->expectsOutputToContain('Delivery Type: broadcast')
            ->assertSuccessful();

        // Assert
        Queue::assertNothingPushed();
    }
}
