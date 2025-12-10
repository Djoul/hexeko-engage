<?php

namespace Tests\Unit\DTOs\Push;

use App\DTOs\Push\PushEventDTO;
use App\Enums\PushEventTypes;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class PushEventDTOTest extends TestCase
{
    #[Test]
    public function it_can_create_dto_from_array(): void
    {
        $occurredAt = Carbon::now();
        $data = [
            'push_notification_id' => 123,
            'push_subscription_id' => 456,
            'event_type' => 'delivered',
            'event_id' => 'event-123-456',
            'event_data' => ['delivered_at' => $occurredAt->toISOString()],
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
            'occurred_at' => $occurredAt,
        ];

        $dto = PushEventDTO::from($data);

        $this->assertInstanceOf(PushEventDTO::class, $dto);
        $this->assertEquals(123, $dto->pushNotificationId);
        $this->assertEquals(456, $dto->pushSubscriptionId);
        $this->assertEquals(PushEventTypes::DELIVERED, $dto->eventType);
        $this->assertEquals('event-123-456', $dto->eventId);
        $this->assertEquals(['delivered_at' => $occurredAt->toISOString()], $dto->eventData);
        $this->assertEquals('192.168.1.1', $dto->ipAddress);
        $this->assertEquals('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', $dto->userAgent);
        $this->assertEquals($occurredAt, $dto->occurredAt);
    }

    #[Test]
    public function it_uses_default_values_for_optional_fields(): void
    {
        $data = [
            'push_notification_id' => 789,
            'event_type' => 'sent',
        ];

        $dto = PushEventDTO::from($data);

        $this->assertEquals(789, $dto->pushNotificationId);
        $this->assertEquals(PushEventTypes::SENT, $dto->eventType);
        $this->assertNull($dto->pushSubscriptionId);
        $this->assertNull($dto->eventId);
        $this->assertEquals([], $dto->eventData);
        $this->assertNull($dto->ipAddress);
        $this->assertNull($dto->userAgent);
        $this->assertInstanceOf(Carbon::class, $dto->occurredAt);
        $this->assertTrue($dto->occurredAt->isToday());
    }

    #[Test]
    public function it_can_convert_to_array(): void
    {
        $occurredAt = Carbon::parse('2025-01-20 14:30:00');
        $data = [
            'push_notification_id' => 111,
            'push_subscription_id' => 222,
            'event_type' => 'opened',
            'event_id' => 'event-abc',
            'event_data' => ['action' => 'view'],
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Chrome/120.0',
            'occurred_at' => $occurredAt,
        ];

        $dto = PushEventDTO::from($data);
        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(111, $array['push_notification_id']);
        $this->assertEquals(222, $array['push_subscription_id']);
        $this->assertEquals('opened', $array['event_type']);
        $this->assertEquals('event-abc', $array['event_id']);
        $this->assertEquals(['action' => 'view'], $array['event_data']);
        $this->assertEquals('10.0.0.1', $array['ip_address']);
        $this->assertEquals('Chrome/120.0', $array['user_agent']);
        $this->assertEquals($occurredAt->toDateTimeString(), $array['occurred_at']);
    }

    #[Test]
    public function it_can_create_webhook_event(): void
    {
        $webhookData = [
            'push_notification_id' => 333,
            'event_type' => 'clicked',
            'event_id' => 'webhook-event-123',
            'event_data' => [
                'button_id' => 'cta',
                'url' => 'https://example.com/action',
            ],
            'ip_address' => '203.0.113.0',
            'user_agent' => 'OneSignal Webhook',
        ];

        $dto = PushEventDTO::from($webhookData);

        $this->assertEquals(333, $dto->pushNotificationId);
        $this->assertEquals(PushEventTypes::CLICKED, $dto->eventType);
        $this->assertEquals('webhook-event-123', $dto->eventId);
        $this->assertArrayHasKey('button_id', $dto->eventData);
        $this->assertEquals('cta', $dto->eventData['button_id']);
    }

    #[Test]
    public function it_can_handle_failure_event(): void
    {
        $data = [
            'push_notification_id' => 444,
            'push_subscription_id' => 555,
            'event_type' => 'failed',
            'event_data' => [
                'error_code' => 'INVALID_SUBSCRIPTION',
                'error_message' => 'Subscription no longer valid',
            ],
        ];

        $dto = PushEventDTO::from($data);

        $this->assertEquals(PushEventTypes::FAILED, $dto->eventType);
        $this->assertArrayHasKey('error_code', $dto->eventData);
        $this->assertEquals('INVALID_SUBSCRIPTION', $dto->eventData['error_code']);
        $this->assertArrayHasKey('error_message', $dto->eventData);
    }

    #[Test]
    public function it_can_create_from_webhook_payload(): void
    {
        $webhookPayload = [
            'notification_id' => 'notif-123',
            'external_id' => 'ext-456',
            'event' => 'delivered',
            'timestamp' => 1737383400,
            'custom_data' => ['key' => 'value'],
        ];

        $dto = PushEventDTO::fromWebhookPayload($webhookPayload, 999);

        $this->assertEquals(999, $dto->pushNotificationId);
        $this->assertEquals(PushEventTypes::DELIVERED, $dto->eventType);
        $this->assertEquals('ext-456', $dto->eventId);
        $this->assertEquals(['key' => 'value'], $dto->eventData['custom_data']);
        $this->assertEquals(Carbon::createFromTimestamp(1737383400), $dto->occurredAt);
    }
}
