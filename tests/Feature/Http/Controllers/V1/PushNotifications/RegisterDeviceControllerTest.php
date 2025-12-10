<?php

namespace Tests\Feature\Http\Controllers\V1\PushNotifications;

use App\Enums\DeviceTypes;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('push')]
#[Group('notification')]
class RegisterDeviceControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private string $endpoint = '/api/v1/push/devices/register';

    #[Test]
    public function it_validates_required_fields(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['subscription_id', 'device_type']);
    }

    #[Test]
    public function it_validates_device_type_enum(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, [
                'subscription_id' => 'test-subscription-id',
                'device_type' => 'invalid-type',
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['device_type']);
    }

    #[Test]
    public function it_registers_device_successfully(): void
    {
        // Arrange
        // Clean any existing subscriptions with this ID (including soft deleted ones)
        PushSubscription::withTrashed()
            ->where('subscription_id', 'onesignal-subscription-unique-123')
            ->forceDelete();

        $this->auth = $this->createAuthUser();
        $uniqueSubscriptionId = 'onesignal-subscription-unique-123';

        $payload = [
            'subscription_id' => $uniqueSubscriptionId,
            'device_type' => DeviceTypes::IOS,
            'device_model' => 'iPhone 15 Pro',
            'device_os' => 'iOS 17.0',
            'app_version' => '1.2.3',
            'timezone' => 'Europe/Paris',
            'language' => 'fr-FR',
            'tags' => ['premium' => true, 'segment' => 'active'],
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, $payload);

        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'subscription_id',
                    'user_id',
                    'device_type',
                    'device_model',
                    'device_os',
                    'app_version',
                    'timezone',
                    'language',
                    'tags',
                    'push_enabled',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.subscription_id', $uniqueSubscriptionId)
            ->assertJsonPath('data.user_id', $this->auth->id)
            ->assertJsonPath('data.device_type', DeviceTypes::IOS)
            ->assertJsonPath('data.push_enabled', true);

        // Verify database
        $this->assertDatabaseHas('push_subscriptions', [
            'subscription_id' => $uniqueSubscriptionId,
            'user_id' => $this->auth->id,
            'device_type' => DeviceTypes::IOS,
        ]);
    }

    #[Test]
    public function it_updates_existing_subscription(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $existingSubscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
            'subscription_id' => 'existing-sub-123',
            'device_type' => DeviceTypes::WEB,
            'app_version' => '1.0.0',
        ]);

        $payload = [
            'subscription_id' => 'existing-sub-123',
            'device_type' => DeviceTypes::WEB,
            'app_version' => '2.0.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson($this->endpoint, $payload);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.id', $existingSubscription->id)
            ->assertJsonPath('data.app_version', '2.0.0');

        // Verify update in database
        $this->assertDatabaseHas('push_subscriptions', [
            'id' => $existingSubscription->id,
            'app_version' => '2.0.0',
        ]);
    }

    #[Test]
    public function it_accepts_all_valid_device_types(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $deviceTypes = [
            DeviceTypes::WEB,
            DeviceTypes::IOS,
            DeviceTypes::ANDROID,
            DeviceTypes::DESKTOP,
        ];

        foreach ($deviceTypes as $index => $deviceType) {
            // Act
            $response = $this->actingAs($this->auth)
                ->postJson($this->endpoint, [
                    'subscription_id' => "test-sub-{$index}",
                    'device_type' => $deviceType,
                ]);

            // Assert
            $response->assertSuccessful()
                ->assertJsonPath('data.device_type', $deviceType);
        }
    }

    #[Test]
    public function it_returns_paginated_list_when_getting_user_devices(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        // Create multiple subscriptions
        for ($i = 1; $i <= 5; $i++) {
            PushSubscription::factory()->create([
                'user_id' => $this->auth->id,
                'subscription_id' => "sub-{$i}",
            ]);
        }

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/push/devices');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'subscription_id',
                        'user_id',
                        'device_type',
                        'push_enabled',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'total',
                    'per_page',
                ],
            ])
            ->assertJsonCount(5, 'data');
    }
}
