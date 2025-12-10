<?php

namespace Tests\Unit\Actions\Push;

use App\Actions\Push\SendPushNotificationAction;
use App\DTOs\Push\PushNotificationDTO;
use App\Enums\DeviceTypes;
use App\Enums\NotificationTypes;
use App\Jobs\Push\SendPushNotificationJob;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class SendPushNotificationActionTest extends TestCase
{
    use DatabaseTransactions;

    private SendPushNotificationAction $action;

    private MockInterface $oneSignalService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->oneSignalService = $this->mock(OneSignalService::class);
        $this->action = new SendPushNotificationAction($this->oneSignalService);
    }

    #[Test]
    public function it_sends_notification_to_specific_users(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser(['email' => 'user1@example.com']);
        $user2 = ModelFactory::createUser(['email' => 'user2@example.com']);

        // Create subscriptions for users
        PushSubscription::create([
            'user_id' => $user1->id,
            'subscription_id' => 'user1_device',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone 14',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        PushSubscription::create([
            'user_id' => $user2->id,
            'subscription_id' => 'user2_device',
            'device_type' => DeviceTypes::ANDROID,
            'device_model' => 'Pixel 8',
            'device_os' => 'Android 14',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        $dto = PushNotificationDTO::from([
            'title' => 'Test Notification',
            'body' => 'This is a test notification',
            'type' => 'transaction',
            'recipient_ids' => [$user1->id, $user2->id],
            'data' => ['order_id' => '12345'],
        ]);

        $this->oneSignalService
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(Mockery::on(function (array $notification): bool {
                return $notification['headings']['en'] === 'Test Notification'
                    && $notification['contents']['en'] === 'This is a test notification';
            }), ['user1_device', 'user2_device'])
            ->andReturn(['id' => 'onesignal_notification_123']);

        // Act
        $notification = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushNotification::class, $notification);
        $this->assertEquals('Test Notification', $notification->title);
        $this->assertEquals('This is a test notification', $notification->body);
        $this->assertEquals(NotificationTypes::TRANSACTION, $notification->type);
        $this->assertEquals(['order_id' => '12345'], $notification->data);
        $this->assertEquals(2, $notification->recipient_count);
        $this->assertEquals('onesignal_notification_123', $notification->external_id);

        $this->assertDatabaseHas('push_notifications', [
            'title' => 'Test Notification',
            'external_id' => 'onesignal_notification_123',
        ]);
    }

    #[Test]
    public function it_broadcasts_notification_to_all_users(): void
    {
        // Arrange
        // Create multiple users with subscriptions
        $user1 = ModelFactory::createUser(['email' => 'user1@example.com']);
        $user2 = ModelFactory::createUser(['email' => 'user2@example.com']);
        $user3 = ModelFactory::createUser(['email' => 'user3@example.com']);

        PushSubscription::create([
            'user_id' => $user1->id,
            'subscription_id' => 'device1',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        PushSubscription::create([
            'user_id' => $user2->id,
            'subscription_id' => 'device2',
            'device_type' => DeviceTypes::ANDROID,
            'device_model' => 'Pixel',
            'device_os' => 'Android 14',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        // User 3 has push disabled - should not receive
        PushSubscription::create([
            'user_id' => $user3->id,
            'subscription_id' => 'device3',
            'device_type' => DeviceTypes::WEB,
            'device_model' => 'Chrome',
            'device_os' => 'Windows',
            'app_version' => '1.0.0',
            'push_enabled' => false,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        $dto = PushNotificationDTO::from([
            'title' => 'System Announcement',
            'body' => 'Important system update',
            'type' => 'system',
            'recipient_ids' => [], // Empty means broadcast to all
            'priority' => 'high',
        ]);

        $this->oneSignalService
            ->shouldReceive('broadcast')
            ->once()
            ->with(Mockery::on(function (array $notification): bool {
                return $notification['headings']['en'] === 'System Announcement'
                    && $notification['priority'] === 10; // High priority
            }))
            ->andReturn(['id' => 'broadcast_123']);

        // Act
        $notification = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushNotification::class, $notification);
        $this->assertEquals('System Announcement', $notification->title);
        $this->assertEquals('broadcast', $notification->delivery_type);
        $this->assertEquals('broadcast_123', $notification->external_id);
        $this->assertEquals(2, $notification->recipient_count); // Only 2 users have push enabled
    }

    #[Test]
    public function it_sends_notification_with_action_buttons(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'user@example.com']);

        PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'user_device',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        $dto = PushNotificationDTO::from([
            'title' => 'New Order',
            'body' => 'You have a new order #1234',
            'type' => 'transaction',
            'recipient_ids' => [$user->id],
            'url' => 'https://example.com/orders/1234',
            'buttons' => [
                ['id' => 'view', 'text' => 'View Order', 'url' => 'https://example.com/orders/1234'],
                ['id' => 'cancel', 'text' => 'Cancel'],
            ],
        ]);

        $this->oneSignalService
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(Mockery::on(function (array $notification): bool {
                return isset($notification['buttons'])
                    && count($notification['buttons']) === 2
                    && $notification['url'] === 'https://example.com/orders/1234';
            }), ['user_device'])
            ->andReturn(['id' => 'action_notification_123']);

        // Act
        $notification = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushNotification::class, $notification);
        $this->assertEquals('https://example.com/orders/1234', $notification->url);
        $this->assertCount(2, $notification->buttons);
    }

    #[Test]
    public function it_schedules_notification_for_future_delivery(): void
    {
        // Arrange
        Queue::fake();

        $user = ModelFactory::createUser(['email' => 'user@example.com']);

        PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'user_device',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        $scheduledTime = now()->addHours(2)->toDateTimeString();
        $dto = PushNotificationDTO::from([
            'title' => 'Scheduled Reminder',
            'body' => 'Don\'t forget your appointment',
            'type' => 'reminder',
            'recipient_ids' => [$user->id],
            'scheduled_at' => $scheduledTime,
        ]);

        // Act
        $notification = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushNotification::class, $notification);
        $this->assertEquals('scheduled', $notification->status);
        $this->assertEquals($scheduledTime, $notification->scheduled_at);

        Queue::assertPushed(SendPushNotificationJob::class);
    }

    #[Test]
    public function it_filters_out_users_without_subscriptions(): void
    {
        // Arrange
        $userWithDevice = ModelFactory::createUser(['email' => 'with-device@example.com']);
        $userWithoutDevice = ModelFactory::createUser(['email' => 'without-device@example.com']);

        PushSubscription::create([
            'user_id' => $userWithDevice->id,
            'subscription_id' => 'device_123',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        // userWithoutDevice has no subscription

        $dto = PushNotificationDTO::from([
            'title' => 'Test',
            'body' => 'Test notification',
            'type' => 'alert',
            'recipient_ids' => [$userWithDevice->id, $userWithoutDevice->id],
        ]);

        $this->oneSignalService
            ->shouldReceive('sendToUsers')
            ->once()
            ->with(Mockery::any(), ['device_123']) // Only one device
            ->andReturn(['id' => 'notification_123']);

        // Act
        $notification = $this->action->execute($dto);

        // Assert
        $this->assertEquals(1, $notification->recipient_count); // Only 1 user with device
        $this->assertEquals(1, $notification->device_count);
    }
}
