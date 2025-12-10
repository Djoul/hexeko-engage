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
class UnregisterDeviceControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private string $endpoint = '/api/v1/push/devices';

    #[Test]
    public function it_requires_authentication_for_user_unregistration(): void
    {
        // Act
        $response = $this->deleteJson("{$this->endpoint}/test-subscription-id");

        // Assert
        $response->assertUnauthorized();
    }

    #[Test]
    public function it_unregisters_specific_device_by_subscription_id(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'device-to-remove',
        ]);

        // Keep another subscription
        $keepSubscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'device-to-keep',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("{$this->endpoint}/device-to-remove");

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => 'Device unregistered successfully',
                ],
            ]);

        // Verify database
        $this->assertSoftDeleted('push_subscriptions', [
            'id' => $subscription->id,
        ]);
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $keepSubscription->id,
        ]);
    }

    #[Test]
    public function it_unregisters_all_user_devices(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Create multiple subscriptions
        for ($i = 1; $i <= 3; $i++) {
            PushSubscription::factory()->create([
                'user_id' => $this->auth->id,
                'subscription_id' => "device-{$i}",
            ]);
        }

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("{$this->endpoint}/all");

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => '3 devices unregistered successfully',
                    'count' => 3,
                ],
            ]);

        // Verify all devices removed
        $this->assertSoftDeleted('push_subscriptions', [
            'user_id' => $this->auth->id,
        ]);
    }

    #[Test]
    public function it_returns_not_found_for_non_existent_subscription(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("{$this->endpoint}/non-existent-device");

        // Assert
        $response->assertNotFound()
            ->assertJson([
                'message' => 'Device not found',
            ]);
    }

    #[Test]
    public function it_prevents_user_from_unregistering_another_users_device(): void
    {
        // Arrange
        $user1 = $this->createAuthUser();
        $user2 = ModelFactory::createUser(['email' => 'other@example.com']);

        $subscription = PushSubscription::factory()->create([
            'user_id' => $user2->id,
            'subscription_id' => 'other-user-device',
        ]);

        // Act - user1 tries to unregister user2's device
        $response = $this->actingAs($user1)
            ->deleteJson("{$this->endpoint}/other-user-device");

        // Assert
        $response->assertNotFound()
            ->assertJson([
                'message' => 'Device not found',
            ]);

        // Verify device still exists
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $subscription->id,
        ]);
    }

    #[Test]
    public function it_returns_zero_when_user_has_no_devices(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("{$this->endpoint}/all");

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => '0 devices unregistered successfully',
                    'count' => 0,
                ],
            ]);
    }

    #[Test]
    public function it_soft_deletes_instead_of_hard_delete(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'soft-delete-test',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson("{$this->endpoint}/soft-delete-test");

        // Assert
        $response->assertOk();

        // Verify soft deleted
        $this->assertSoftDeleted('push_subscriptions', [
            'id' => $subscription->id,
        ]);

        // Can still query with trashed
        $trashedSubscription = PushSubscription::withTrashed()->find($subscription->id);
        $this->assertNotNull($trashedSubscription);
        $this->assertNotNull($trashedSubscription->deleted_at);
    }
}
