<?php

namespace Tests\Unit\Actions\Push;

use App\Actions\Push\UnregisterDeviceAction;
use App\Enums\DeviceTypes;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class UnregisterDeviceActionTest extends TestCase
{
    use DatabaseTransactions;

    private UnregisterDeviceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(UnregisterDeviceAction::class);
    }

    #[Test]
    public function it_unregisters_device_by_subscription_id(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_to_remove',
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

        // Act
        $result = $this->action->execute($subscription->subscription_id, $user->id);

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('push_subscriptions', [
            'subscription_id' => 'device_to_remove',
        ]);
    }

    #[Test]
    public function it_unregisters_all_devices_for_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'multidevice@example.com']);

        // Create multiple devices for the user
        PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_1',
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
            'user_id' => $user->id,
            'subscription_id' => 'device_2',
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

        PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_3',
            'device_type' => DeviceTypes::WEB,
            'device_model' => 'Chrome',
            'device_os' => 'Windows',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        // Create another user's device to ensure it's not deleted
        $otherUser = ModelFactory::createUser(['email' => 'other@example.com']);
        PushSubscription::create([
            'user_id' => $otherUser->id,
            'subscription_id' => 'other_device',
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

        // Act
        $result = $this->action->executeForUser($user);

        // Assert
        $this->assertEquals(3, $result); // Should have deleted 3 devices
        $this->assertSoftDeleted('push_subscriptions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $otherUser->id]);
    }

    #[Test]
    public function it_returns_false_when_subscription_not_found(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);

        // Act
        $result = $this->action->execute('non_existent_subscription', $user->id);

        // Assert
        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_zero_when_user_has_no_devices(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'nodevices@example.com']);

        // Act
        $result = $this->action->executeForUser($user);

        // Assert
        $this->assertEquals(0, $result);
    }

    #[Test]
    public function it_unregisters_guest_device(): void
    {
        // Arrange
        PushSubscription::create([
            'user_id' => null,
            'subscription_id' => 'guest_device',
            'device_type' => DeviceTypes::WEB,
            'device_model' => 'Chrome',
            'device_os' => 'Windows',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => ['guest' => true],
            'metadata' => [],
            'last_active_at' => now(),
        ]);

        // Act
        $result = $this->action->execute('guest_device');

        // Assert
        $this->assertTrue($result);
        $this->assertSoftDeleted('push_subscriptions', [
            'subscription_id' => 'guest_device',
        ]);
    }
}
