<?php

namespace Tests\Unit\Actions\Push;

use App\Actions\Push\ProcessWebhookEventAction;
use App\DTOs\Push\PushEventDTO;
use App\Enums\PushEventTypes;
use App\Models\PushEvent;
use App\Models\PushNotification;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('push')]
#[Group('actions')]
class ProcessWebhookEventActionTest extends TestCase
{
    use DatabaseTransactions;

    private ProcessWebhookEventAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProcessWebhookEventAction;
    }

    #[Test]
    public function it_processes_delivered_event_successfully(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-123',
            'status' => 'sent',
            'delivered_count' => 0,
        ]);

        $subscription = PushSubscription::factory()->create();

        $dto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'push_subscription_id' => $subscription->id,
            'event_type' => PushEventTypes::DELIVERED,
            'event_id' => 'event-123',
            'event_data' => ['custom' => 'data'],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals($notification->id, $event->push_notification_id);
        $this->assertEquals($subscription->id, $event->push_subscription_id);
        $this->assertEquals(PushEventTypes::DELIVERED, $event->event_type->value);
        $this->assertEquals('event-123', $event->event_id);
        $this->assertEquals(['custom' => 'data'], $event->event_data);

        // Check notification counter updated
        $notification->refresh();
        $this->assertEquals(1, $notification->delivered_count);
    }

    #[Test]
    public function it_processes_opened_event_and_updates_counter(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-456',
            'status' => 'sent',
            'opened_count' => 2,
        ]);

        $dto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::OPENED,
            'event_id' => 'event-456',
        ]);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals(PushEventTypes::OPENED, $event->event_type->value);

        // Check notification counter incremented
        $notification->refresh();
        $this->assertEquals(3, $notification->opened_count);
    }

    #[Test]
    public function it_processes_clicked_event_and_updates_counter(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-789',
            'status' => 'sent',
            'clicked_count' => 5,
        ]);

        $dto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::CLICKED,
            'event_id' => 'event-789',
        ]);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals(PushEventTypes::CLICKED, $event->event_type->value);

        // Check notification counter incremented
        $notification->refresh();
        $this->assertEquals(6, $notification->clicked_count);
    }

    #[Test]
    public function it_processes_failed_event_and_updates_status(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-fail',
            'status' => 'sent',
        ]);

        $dto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::FAILED,
            'event_id' => 'event-fail',
            'event_data' => ['error' => 'Network error'],
        ]);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals(PushEventTypes::FAILED, $event->event_type->value);
        $this->assertEquals(['error' => 'Network error'], $event->event_data);

        // Check notification status updated to failed
        $notification->refresh();
        $this->assertEquals('failed', $notification->status);
    }

    #[Test]
    public function it_processes_dismissed_event(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-dismiss',
            'status' => 'sent',
        ]);

        $dto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::DISMISSED,
            'event_id' => 'event-dismiss',
        ]);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals(PushEventTypes::DISMISSED, $event->event_type->value);

        // Status should remain unchanged for dismissed
        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    #[Test]
    public function it_processes_sent_event(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-sent',
            'status' => 'sending',
        ]);

        $dto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::SENT,
            'event_id' => 'event-sent',
        ]);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals(PushEventTypes::SENT, $event->event_type->value);

        // Check notification status updated to sent
        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
    }

    #[Test]
    public function it_handles_webhook_payload_format(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-webhook',
            'status' => 'sent',
        ]);

        // Simulate OneSignal webhook payload format
        $webhookPayload = [
            'event' => 'delivered',
            'external_id' => 'onesignal-webhook',
            'custom_data' => ['user_id' => 123],
            'timestamp' => time(),
        ];

        $dto = PushEventDTO::fromWebhookPayload($webhookPayload, $notification->id);

        // Act
        $event = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(PushEvent::class, $event);
        $this->assertEquals(PushEventTypes::DELIVERED, $event->event_type->value);
        $this->assertEquals('onesignal-webhook', $event->event_id);
        $this->assertArrayHasKey('custom_data', $event->event_data);
    }

    #[Test]
    public function it_logs_warning_when_notification_not_found(): void
    {
        // Arrange
        // Create a valid notification first to satisfy foreign key constraint
        PushNotification::factory()->create();

        // Use a different UUID for testing that doesn't exist
        $missingNotificationId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        Log::shouldReceive('warning')
            ->once()
            ->with('Push notification not found for webhook event', [
                'notification_id' => $missingNotificationId,
                'event_type' => PushEventTypes::DELIVERED,
            ]);

        // Create event with missing notification ID
        // Since we can't insert with invalid foreign key, we'll test the logging directly
        $event = new PushEvent([
            'push_notification_id' => $missingNotificationId,
            'event_type' => PushEventTypes::DELIVERED,
            'event_id' => 'event-missing',
            'event_data' => [],
            'occurred_at' => now(),
        ]);

        // Act - call the private method directly to test logging
        $reflection = new ReflectionClass($this->action);
        $method = $reflection->getMethod('updateNotificationMetrics');
        $method->setAccessible(true);
        $method->invoke($this->action, $event);

        // Assert
        $this->assertEquals($missingNotificationId, $event->push_notification_id);
    }

    #[Test]
    public function it_processes_multiple_events_for_same_notification(): void
    {
        // Arrange
        $notification = PushNotification::factory()->create([
            'external_id' => 'onesignal-multi',
            'status' => 'sent',
            'delivered_count' => 0,
            'opened_count' => 0,
            'clicked_count' => 0,
        ]);

        // Process delivered event
        $deliveredDto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::DELIVERED,
            'event_id' => 'event-1',
        ]);
        $this->action->execute($deliveredDto);

        // Process opened event
        $openedDto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::OPENED,
            'event_id' => 'event-2',
        ]);
        $this->action->execute($openedDto);

        // Process clicked event
        $clickedDto = PushEventDTO::from([
            'push_notification_id' => $notification->id,
            'event_type' => PushEventTypes::CLICKED,
            'event_id' => 'event-3',
        ]);
        $this->action->execute($clickedDto);

        // Assert all counters updated
        $notification->refresh();
        $this->assertEquals(1, $notification->delivered_count);
        $this->assertEquals(1, $notification->opened_count);
        $this->assertEquals(1, $notification->clicked_count);

        // Assert all events created
        $events = PushEvent::where('push_notification_id', $notification->id)->get();
        $this->assertCount(3, $events);

        // Check that all event types are present (order doesn't matter)
        $eventTypes = $events->pluck('event_type')->map(fn ($type) => $type->value)->toArray();
        $this->assertContains(PushEventTypes::DELIVERED, $eventTypes);
        $this->assertContains(PushEventTypes::OPENED, $eventTypes);
        $this->assertContains(PushEventTypes::CLICKED, $eventTypes);
    }
}
