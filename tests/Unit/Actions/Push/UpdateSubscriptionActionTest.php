<?php

namespace Tests\Unit\Actions\Push;

use App\Actions\Push\UpdateSubscriptionAction;
use App\Enums\DeviceTypes;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class UpdateSubscriptionActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateSubscriptionAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(UpdateSubscriptionAction::class);
    }

    #[Test]
    public function it_updates_push_preferences(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_123',
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone 14',
            'device_os' => 'iOS 17',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => ['role' => 'user'],
            'metadata' => ['locale' => 'en'],
            'last_active_at' => now()->subDays(1),
        ]);

        $preferences = [
            'push_enabled' => false,
            'sound_enabled' => true,
            'vibration_enabled' => false,
        ];

        // Act
        $updatedSubscription = $this->action->updatePreferences($subscription->subscription_id, $preferences);

        // Assert
        $this->assertInstanceOf(PushSubscription::class, $updatedSubscription);
        $this->assertFalse($updatedSubscription->push_enabled);
        $this->assertTrue($updatedSubscription->sound_enabled);
        $this->assertFalse($updatedSubscription->vibration_enabled);
        $this->assertGreaterThan($subscription->last_active_at, $updatedSubscription->last_active_at);
    }

    #[Test]
    public function it_updates_device_tags(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_456',
            'device_type' => DeviceTypes::ANDROID,
            'device_model' => 'Pixel 8',
            'device_os' => 'Android 14',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => ['role' => 'user'],
            'metadata' => [],
            'last_active_at' => now()->subDays(1),
        ]);

        $tags = [
            'role' => 'premium',
            'division' => 'sales',
            'beta' => true,
        ];

        // Act
        $updatedSubscription = $this->action->updateTags($subscription->subscription_id, $tags);

        // Assert
        $this->assertInstanceOf(PushSubscription::class, $updatedSubscription);
        $this->assertEquals($tags, $updatedSubscription->tags);
        $this->assertGreaterThan($subscription->last_active_at, $updatedSubscription->last_active_at);
    }

    #[Test]
    public function it_updates_device_metadata(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $subscription = PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_789',
            'device_type' => DeviceTypes::WEB,
            'device_model' => 'Chrome',
            'device_os' => 'Windows',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => [],
            'metadata' => ['browser' => 'chrome'],
            'last_active_at' => now()->subDays(1),
        ]);

        $metadata = [
            'browser' => 'chrome',
            'version' => '120.0',
            'platform' => 'windows',
            'language' => 'fr-FR',
        ];

        // Act
        $updatedSubscription = $this->action->updateMetadata($subscription->subscription_id, $metadata);

        // Assert
        $this->assertInstanceOf(PushSubscription::class, $updatedSubscription);
        $this->assertEquals($metadata, $updatedSubscription->metadata);
        $this->assertGreaterThan($subscription->last_active_at, $updatedSubscription->last_active_at);
    }

    #[Test]
    public function it_returns_null_when_subscription_not_found(): void
    {
        // Act
        $result = $this->action->updatePreferences('non_existent', ['push_enabled' => false]);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function it_updates_all_preferences_for_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'multidevice@example.com']);

        // Create multiple devices for the user
        $subscription1 = PushSubscription::create([
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
            'last_active_at' => now()->subDays(1),
        ]);

        $subscription2 = PushSubscription::create([
            'user_id' => $user->id,
            'subscription_id' => 'device_2',
            'device_type' => DeviceTypes::ANDROID,
            'device_model' => 'Pixel',
            'device_os' => 'Android 14',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => false,
            'vibration_enabled' => true,
            'tags' => [],
            'metadata' => [],
            'last_active_at' => now()->subDays(1),
        ]);

        $preferences = [
            'push_enabled' => false,
            'sound_enabled' => false,
            'vibration_enabled' => false,
        ];

        // Act
        $count = $this->action->updatePreferencesForUser($user, $preferences);

        // Assert
        $this->assertEquals(2, $count);

        $subscription1->refresh();
        $subscription2->refresh();

        $this->assertFalse($subscription1->push_enabled);
        $this->assertFalse($subscription1->sound_enabled);
        $this->assertFalse($subscription1->vibration_enabled);

        $this->assertFalse($subscription2->push_enabled);
        $this->assertFalse($subscription2->sound_enabled);
        $this->assertFalse($subscription2->vibration_enabled);
    }
}
