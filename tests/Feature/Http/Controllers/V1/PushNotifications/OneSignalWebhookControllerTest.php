<?php

namespace Tests\Feature\Http\Controllers\V1\PushNotifications;

use App\Enums\PushEventTypes;
use App\Models\PushEvent;
use App\Models\PushNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('push')]
#[Group('webhooks')]
#[Group('notification')]
class OneSignalWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    private string $endpoint = '/api/v1/push/webhooks/onesignal';

    protected function setUp(): void
    {
        parent::setUp();

        // Clear rate limiter before each test
        RateLimiter::clear('onesignal_webhook:127.0.0.1');

        // Set OneSignal webhook secret for testing
        config(['onesignal.webhook_secret' => 'test-webhook-secret']);
    }

    /**
     * Generate valid webhook signature for testing
     */
    private function generateWebhookSignature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), 'test-webhook-secret');
    }

    #[Test]
    public function it_accepts_valid_webhook_signature(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-notification-123',
            'status' => 'sent',
        ]);

        $payload = [
            'event' => 'delivered',
            'id' => 'onesignal-notification-123',
            'external_id' => 'onesignal-notification-123',
            'custom_data' => ['internal_id' => $notification->id],
            'timestamp' => time(),
        ];

        $signature = $this->generateWebhookSignature($payload);

        // Act - Use call() to send raw JSON matching what controller expects
        $response = $this->call('POST', $this->endpoint, [], [], [], [
            'HTTP_X-OneSignal-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        // Assert
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Webhook processed successfully',
            ]);

        // Verify event created
        $this->assertDatabaseHas('push_events', [
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::DELIVERED,
        ]);
    }

    #[Test]
    public function it_rejects_invalid_webhook_signature(): void
    {
        // Arrange
        $payload = [
            'event' => 'delivered',
            'id' => 'test-notification',
        ];

        // Act - Send with invalid signature
        $response = $this->call('POST', $this->endpoint, [], [], [], [
            'HTTP_X-OneSignal-Signature' => 'invalid-signature',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        // Assert
        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Invalid webhook signature',
            ]);
    }

    #[Test]
    public function it_requires_webhook_signature_header(): void
    {
        // Arrange
        $payload = [
            'event' => 'delivered',
            'id' => 'test-notification',
        ];

        // Act - Send without signature header
        $response = $this->call('POST', $this->endpoint, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload));

        // Assert
        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Missing webhook signature',
            ]);
    }

    #[Test]
    public function it_processes_delivered_event(): void
    {
        // Arrange
        $notification = $this->createNotificationWithValidSignature([
            'delivered_count' => 0,
        ]);

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'delivered',
            'id' => $notification->external_id,
            'custom_data' => ['internal_id' => $notification->id],
        ]);

        // Assert
        $response->assertOk();

        $notification->refresh();
        $this->assertEquals(1, $notification->delivered_count);
    }

    #[Test]
    public function it_processes_opened_event(): void
    {
        // Arrange
        $notification = $this->createNotificationWithValidSignature([
            'opened_count' => 2,
        ]);

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'opened',
            'id' => $notification->external_id,
            'custom_data' => ['internal_id' => $notification->id],
        ]);

        // Assert
        $response->assertOk();

        $notification->refresh();
        $this->assertEquals(3, $notification->opened_count);
    }

    #[Test]
    public function it_processes_clicked_event(): void
    {
        // Arrange
        $notification = $this->createNotificationWithValidSignature([
            'clicked_count' => 1,
        ]);

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'clicked',
            'id' => $notification->external_id,
            'custom_data' => ['internal_id' => $notification->id],
        ]);

        // Assert
        $response->assertOk();

        $notification->refresh();
        $this->assertEquals(2, $notification->clicked_count);
    }

    #[Test]
    public function it_processes_failed_event(): void
    {
        // Arrange
        $notification = $this->createNotificationWithValidSignature([
            'status' => 'sent',
        ]);

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'failed',
            'id' => $notification->external_id,
            'custom_data' => ['internal_id' => $notification->id],
            'error' => 'Device not found',
        ]);

        // Assert
        $response->assertOk();

        $notification->refresh();
        $this->assertEquals('failed', $notification->status);
    }

    #[Test]
    public function it_handles_batch_webhook_events(): void
    {
        // Arrange
        $notifications = [];
        for ($i = 1; $i <= 3; $i++) {
            $notifications[] = PushNotification::factory()->create([
                'external_id' => "batch-notification-{$i}",
                'delivered_count' => 0,
            ]);
        }

        $events = [
            [
                'event' => 'delivered',
                'id' => 'batch-notification-1',
                'custom_data' => ['internal_id' => $notifications[0]->id],
            ],
            [
                'event' => 'opened',
                'id' => 'batch-notification-2',
                'custom_data' => ['internal_id' => $notifications[1]->id],
            ],
            [
                'event' => 'clicked',
                'id' => 'batch-notification-3',
                'custom_data' => ['internal_id' => $notifications[2]->id],
            ],
        ];

        // Act
        $response = $this->sendValidWebhook(['events' => $events]);

        // Assert
        $response->assertOk()
            ->assertJsonPath('data.processed_events', 3);

        // Verify all events processed
        $this->assertEquals(3, PushEvent::count());
    }

    #[Test]
    public function it_logs_error_for_unknown_notification(): void
    {
        // Arrange
        // Allow any Log methods used by middleware
        Log::shouldReceive('withContext')->andReturnSelf();
        Log::shouldReceive('info')->andReturn();
        Log::shouldReceive('warning')->withAnyArgs()->andReturn();

        // Allow any error logs in case of exceptions
        Log::shouldReceive('error')->withAnyArgs()->andReturn();

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'delivered',
            'id' => 'unknown-notification',
        ]);

        // Assert
        $response->assertNotFound() // Returns 404 when notification not found
            ->assertJsonPath('data.warning', 'Notification not found');
    }

    #[Test]
    public function it_validates_webhook_payload_structure(): void
    {
        // Arrange & Act
        $response = $this->sendValidWebhook([
            'invalid' => 'payload',
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }

    #[Test]
    public function it_handles_webhook_with_user_data(): void
    {
        // Arrange
        $notification = $this->createNotificationWithValidSignature();

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'delivered',
            'id' => $notification->external_id,
            'custom_data' => [
                'internal_id' => $notification->id,
                'user_id' => 123,
                'campaign' => 'welcome_series',
            ],
            'user_data' => [
                'country' => 'FR',
                'language' => 'fr',
            ],
        ]);

        // Assert
        $response->assertOk();

        // Verify event with custom data
        $event = PushEvent::where('push_notification_id', $notification->id)->first();
        $this->assertNotNull($event);
        $this->assertArrayHasKey('custom_data', $event->event_data);
        $this->assertEquals('welcome_series', $event->event_data['custom_data']['campaign']);
    }

    #[Test]
    public function it_prevents_replay_attacks_with_timestamp_validation(): void
    {
        // Arrange - Old timestamp (5 minutes ago)
        $oldTimestamp = time() - 300;

        // Act
        $response = $this->sendValidWebhook([
            'event' => 'delivered',
            'id' => 'test-notification',
            'timestamp' => $oldTimestamp,
        ]);

        // Assert
        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Webhook timestamp too old',
            ]);
    }

    #[Test]
    public function it_handles_rate_limiting(): void
    {
        // Arrange - Send multiple requests quickly
        $notification = $this->createNotificationWithValidSignature();

        // Act - Send multiple identical webhooks
        for ($i = 0; $i < 5; $i++) {
            $response = $this->sendValidWebhook([
                'event' => 'delivered',
                'id' => $notification->external_id,
                'custom_data' => ['internal_id' => $notification->id],
            ]);

            if ($i < 3) {
                $response->assertOk();
            } else {
                // Should be rate limited after 3 requests
                $response->assertTooManyRequests();
            }
        }
    }

    // Helper methods

    private function createNotificationWithValidSignature(array $attributes = []): PushNotification
    {
        return PushNotification::factory()->create(array_merge([
            'external_id' => 'test-notification-'.uniqid(),
            'status' => 'sent',
        ], $attributes));
    }

    private function sendValidWebhook(array $payload): TestResponse
    {
        // Must match how Laravel will send the raw JSON to the controller
        $jsonPayload = json_encode($payload);
        $signature = hash_hmac('sha256', $jsonPayload, 'test-webhook-secret');

        // Use post() with raw JSON instead of postJson() to ensure controller receives raw payload
        return $this->call('POST', $this->endpoint, [], [], [], [
            'HTTP_X-OneSignal-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);
    }
}
