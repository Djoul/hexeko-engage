<?php

namespace Tests\Feature\Http\Controllers\V1\PushNotifications;

use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('push')]
#[Group('notification')]
class UpdatePreferencesControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private string $endpoint = '/api/v1/push/preferences';

    #[Test]
    public function it_requires_authentication(): void
    {
        // Act
        $response = $this->putJson($this->endpoint, []);

        // Assert
        $response->assertUnauthorized();
    }

    #[Test]
    public function it_updates_push_enabled_preference(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'test-subscription',
            'push_enabled' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson($this->endpoint, [
                'push_enabled' => false,
            ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => 'Preferences updated successfully',
                    'updated_devices' => 1,
                ],
            ]);

        // Verify database
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'push_enabled' => false,
        ]);
    }

    #[Test]
    public function it_updates_specific_device_preferences(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'specific-device',
            'push_enabled' => true,
            'tags' => ['old' => 'value'],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson("{$this->endpoint}/specific-device", [
                'push_enabled' => false,
                'tags' => ['updated' => true, 'segment' => 'premium'],
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'subscription_id',
                    'push_enabled',
                    'tags',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonPath('data.tags.updated', true)
            ->assertJsonPath('data.tags.segment', 'premium');

        // Verify database
        $subscription->refresh();
        $this->assertFalse($subscription->push_enabled);
        $this->assertEquals(['updated' => true, 'segment' => 'premium'], $subscription->tags);
    }

    #[Test]
    public function it_updates_notification_topic_preferences(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'topic-test',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson("{$this->endpoint}/topic-test", [
                'notification_preferences' => [
                    'marketing' => true,
                    'transactions' => true,
                    'reminders' => false,
                    'system' => true,
                ],
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.notification_preferences.marketing', true)
            ->assertJsonPath('data.notification_preferences.reminders', false);

        // Verify database
        $subscription->refresh();
        $preferences = $subscription->notification_preferences;
        $this->assertTrue($preferences['marketing']);
        $this->assertFalse($preferences['reminders']);
    }

    #[Test]
    public function it_updates_timezone_and_language(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'locale-test',
            'timezone' => 'UTC',
            'language' => 'en-US',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson("{$this->endpoint}/locale-test", [
                'timezone' => 'Europe/Paris',
                'language' => 'fr-FR',
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.timezone', 'Europe/Paris')
            ->assertJsonPath('data.language', 'fr-FR');

        // Verify database
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'timezone' => 'Europe/Paris',
            'language' => 'fr-FR',
        ]);
    }

    #[Test]
    public function it_validates_timezone_format(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'validation-test',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson("{$this->endpoint}/validation-test", [
                'timezone' => 'Invalid/Timezone',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone']);
    }

    #[Test]
    public function it_validates_language_format(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'lang-test',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson("{$this->endpoint}/lang-test", [
                'language' => 'invalid-format',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    #[Test]
    public function it_prevents_updating_another_users_preferences(): void
    {
        // Arrange
        $user1 = $this->createAuthUser();
        $user2 = ModelFactory::createUser(['email' => 'other@example.com']);

        $subscription = PushSubscription::factory()->create([
            'user_id' => $user2->id,
            'subscription_id' => 'other-user-device',
            'push_enabled' => true,
        ]);

        // Act - user1 tries to update user2's preferences
        $response = $this->actingAs($user1)
            ->putjson("{$this->endpoint}/other-user-device", [
                'push_enabled' => false,
            ]);

        // Assert
        $response->assertNotFound()
            ->assertJson([
                'message' => 'Device not found',
            ]);

        // Verify unchanged
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
            'push_enabled' => true,
        ]);
    }

    #[Test]
    public function it_updates_all_user_devices_when_no_subscription_specified(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Create multiple subscriptions
        $subscriptions = [];
        for ($i = 1; $i <= 3; $i++) {
            $subscriptions[] = PushSubscription::factory()->create([
                'user_id' => $this->auth->id,
                'subscription_id' => "device-{$i}",
                'push_enabled' => true,
            ]);
        }

        // Act
        $response = $this->actingAs($this->auth)
            ->putjson($this->endpoint, [
                'push_enabled' => false,
            ]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.updated_devices', 3);

        // Verify all devices updated
        foreach ($subscriptions as $subscription) {
            $this->assertDatabaseHas('push_subscriptions', [
                'id' => $subscription->id,
                'push_enabled' => false,
            ]);
        }
    }

    #[Test]
    public function it_returns_current_preferences(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'get-prefs-test',
            'push_enabled' => true,
            'notification_preferences' => [
                'marketing' => false,
                'transactions' => true,
            ],
            'tags' => ['premium' => true],
            'timezone' => 'Europe/London',
            'language' => 'en-GB',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("{$this->endpoint}/get-prefs-test");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'subscription_id',
                    'push_enabled',
                    'notification_preferences',
                    'tags',
                    'timezone',
                    'language',
                    'device_type',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.push_enabled', true)
            ->assertJsonPath('data.notification_preferences.marketing', false)
            ->assertJsonPath('data.notification_preferences.transactions', true)
            ->assertJsonPath('data.tags.premium', true)
            ->assertJsonPath('data.timezone', 'Europe/London')
            ->assertJsonPath('data.language', 'en-GB');
    }

    #[Test]
    public function it_handles_partial_preference_updates(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'partial-test',
            'notification_preferences' => [
                'marketing' => true,
                'transactions' => true,
                'reminders' => false,
            ],
        ]);

        // Act - Only update one preference
        $response = $this->actingAs($this->auth)
            ->putjson("{$this->endpoint}/partial-test", [
                'notification_preferences' => [
                    'marketing' => false, // Change this one
                    // Keep others unchanged
                ],
            ]);

        // Assert
        $response->assertOk();

        // Verify partial update (should merge, not replace)
        $subscription->refresh();
        $preferences = $subscription->notification_preferences;
        $this->assertFalse($preferences['marketing']); // Updated
        $this->assertTrue($preferences['transactions']); // Unchanged
        $this->assertFalse($preferences['reminders']); // Unchanged
    }
}
