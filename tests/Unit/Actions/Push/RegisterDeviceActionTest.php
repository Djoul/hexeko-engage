<?php

namespace Tests\Unit\Actions\Push;

use App\Actions\Push\RegisterDeviceAction;
use App\DTOs\Push\DeviceRegistrationDTO;
use App\Enums\DeviceTypes;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class RegisterDeviceActionTest extends TestCase
{
    use DatabaseTransactions;

    private RegisterDeviceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(RegisterDeviceAction::class);
    }

    #[Test]
    public function it_registers_new_device_for_authenticated_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $dto = DeviceRegistrationDTO::from([
            'subscription_id' => 'onesignal_123456',
            'device_type' => 'ios',
            'device_model' => 'iPhone 14 Pro',
            'device_os' => 'iOS 17.0',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => ['role' => 'beneficiary', 'division' => 'division_1'],
            'metadata' => ['language' => 'fr-FR'],
        ]);

        // Act
        $subscription = $this->action->execute($user, $dto);

        // Assert
        $this->assertInstanceOf(PushSubscription::class, $subscription);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals('onesignal_123456', $subscription->subscription_id);
        $this->assertEquals(DeviceTypes::IOS, $subscription->device_type);
        $this->assertEquals('iPhone 14 Pro', $subscription->device_model);
        $this->assertEquals('iOS 17.0', $subscription->device_os);
        $this->assertEquals('1.0.0', $subscription->app_version);
        $this->assertTrue($subscription->push_enabled);
        $this->assertTrue($subscription->sound_enabled);
        $this->assertFalse($subscription->vibration_enabled);
        $this->assertEquals(['role' => 'beneficiary', 'division' => 'division_1'], $subscription->tags);
        $this->assertEquals(['language' => 'fr-FR'], $subscription->metadata);
        $this->assertNotNull($subscription->last_active_at);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscription_id' => 'onesignal_123456',
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_registers_device_for_guest_user(): void
    {
        // Arrange
        $dto = DeviceRegistrationDTO::from([
            'subscription_id' => 'guest_device_123',
            'device_type' => 'android',
            'device_model' => 'Pixel 8 Pro',
            'device_os' => 'Android 14',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => ['guest' => true],
            'metadata' => ['session' => 'abc123'],
        ]);

        // Act
        $subscription = $this->action->execute(null, $dto);

        // Assert
        $this->assertInstanceOf(PushSubscription::class, $subscription);
        $this->assertNull($subscription->user_id);
        $this->assertEquals('guest_device_123', $subscription->subscription_id);
        $this->assertEquals(DeviceTypes::ANDROID, $subscription->device_type);
        $this->assertEquals(['guest' => true], $subscription->tags);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscription_id' => 'guest_device_123',
            'user_id' => null,
        ]);
    }

    #[Test]
    public function it_updates_existing_subscription_when_duplicate_found(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'existing@example.com']);

        // Create existing subscription
        $existing = PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'existing_123',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone 13',
            'device_os' => 'iOS 16.0',
            'app_version' => '0.9.0',
            'push_enabled' => false,
            'sound_enabled' => false,
            'vibration_enabled' => false,
            'tags' => ['old' => true],
            'metadata' => ['old_data' => 'value'],
            'last_active_at' => now()->subDays(10),
        ]);

        $dto = DeviceRegistrationDTO::from([
            'subscription_id' => 'existing_123',
            'device_type' => 'ios',
            'device_model' => 'iPhone 14 Pro',
            'device_os' => 'iOS 17.0',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => ['new' => true],
            'metadata' => ['new_data' => 'value'],
        ]);

        // Act
        $subscription = $this->action->execute($user, $dto);

        // Assert
        $this->assertEquals($existing->id, $subscription->id);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals('existing_123', $subscription->subscription_id);
        $this->assertEquals('iPhone 14 Pro', $subscription->device_model);
        $this->assertEquals('iOS 17.0', $subscription->device_os);
        $this->assertEquals('1.0.0', $subscription->app_version);
        $this->assertTrue($subscription->push_enabled);
        $this->assertTrue($subscription->sound_enabled);
        $this->assertTrue($subscription->vibration_enabled);
        $this->assertEquals(['new' => true], $subscription->tags);
        $this->assertEquals(['new_data' => 'value'], $subscription->metadata);
        $this->assertGreaterThan($existing->last_active_at, $subscription->last_active_at);

        // Ensure we didn't create a duplicate
        $this->assertEquals(1, PushSubscription::where('subscription_id', 'existing_123')->count());
    }

    #[Test]
    public function it_associates_guest_device_with_user_on_login(): void
    {
        // Arrange
        // Create a guest subscription first
        $guestSubscription = PushSubscription::create([
            'user_id' => null,
            'subscription_id' => 'guest_device_456',
            'device_type' => DeviceTypes::WEB,
            'device_model' => 'Chrome',
            'device_os' => 'Windows',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => ['guest' => true],
            'metadata' => ['browser' => 'chrome'],
            'last_active_at' => now()->subHours(2),
        ]);

        $user = ModelFactory::createUser(['email' => 'newuser@example.com']);

        // User logs in with the same device
        $dto = DeviceRegistrationDTO::from([
            'subscription_id' => 'guest_device_456',
            'device_type' => 'web',
            'device_model' => 'Chrome',
            'device_os' => 'Windows',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => ['role' => 'beneficiary'],
            'metadata' => ['browser' => 'chrome', 'user_logged_in' => true],
        ]);

        // Act
        $subscription = $this->action->execute($user, $dto);

        // Assert
        $this->assertEquals($guestSubscription->id, $subscription->id);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals(['role' => 'beneficiary'], $subscription->tags);
        $this->assertEquals(['browser' => 'chrome', 'user_logged_in' => true], $subscription->metadata);

        // Ensure no duplicate was created
        $this->assertEquals(1, PushSubscription::where('subscription_id', 'guest_device_456')->count());
    }

    #[Test]
    public function it_handles_multiple_devices_per_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'multidevice@example.com']);

        $dto1 = DeviceRegistrationDTO::from([
            'subscription_id' => 'device_phone',
            'device_type' => 'ios',
            'device_model' => 'iPhone 14',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
        ]);

        $dto2 = DeviceRegistrationDTO::from([
            'subscription_id' => 'device_tablet',
            'device_type' => 'android',
            'device_model' => 'iPad Pro',
            'device_os' => 'iPadOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => false,
            'vibration_enabled' => false,
            'tags' => [],
            'metadata' => [],
        ]);

        $dto3 = DeviceRegistrationDTO::from([
            'subscription_id' => 'device_web',
            'device_type' => 'web',
            'device_model' => 'Chrome',
            'device_os' => 'macOS',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => [],
            'metadata' => [],
        ]);

        // Act
        $subscription1 = $this->action->execute($user, $dto1);
        $subscription2 = $this->action->execute($user, $dto2);
        $subscription3 = $this->action->execute($user, $dto3);

        // Assert
        $this->assertEquals(3, $user->pushSubscriptions()->count());
        $this->assertEquals('device_phone', $subscription1->subscription_id);
        $this->assertEquals('device_tablet', $subscription2->subscription_id);
        $this->assertEquals('device_web', $subscription3->subscription_id);

        $this->assertDatabaseHas('push_subscriptions', ['subscription_id' => 'device_phone', 'user_id' => $user->id]);
        $this->assertDatabaseHas('push_subscriptions', ['subscription_id' => 'device_tablet', 'user_id' => $user->id]);
        $this->assertDatabaseHas('push_subscriptions', ['subscription_id' => 'device_web', 'user_id' => $user->id]);
    }
}
