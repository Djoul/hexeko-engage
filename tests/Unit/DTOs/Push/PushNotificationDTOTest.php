<?php

namespace Tests\Unit\DTOs\Push;

use App\DTOs\Push\PushNotificationDTO;
use App\Enums\NotificationTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class PushNotificationDTOTest extends TestCase
{
    #[Test]
    public function it_can_create_dto_from_array(): void
    {
        $data = [
            'notification_id' => 'notif-123-456',
            'type' => 'transaction',
            'title' => 'Payment Received',
            'body' => 'Your payment of €100 has been received',
            'url' => 'https://example.com/payment/123',
            'image' => 'https://example.com/images/payment.png',
            'icon' => 'https://example.com/icons/payment.png',
            'data' => ['transaction_id' => '123', 'amount' => 100],
            'buttons' => [
                ['id' => 'view', 'text' => 'View Details', 'url' => 'https://example.com/payment/123'],
                ['id' => 'dismiss', 'text' => 'Dismiss'],
            ],
            'priority' => 'high',
            'ttl' => 3600,
            'recipient_ids' => ['user-1', 'user-2'],
            'topic_ids' => ['topic-1'],
        ];

        $dto = PushNotificationDTO::from($data);

        $this->assertInstanceOf(PushNotificationDTO::class, $dto);
        $this->assertEquals('notif-123-456', $dto->notificationId);
        $this->assertEquals(NotificationTypes::TRANSACTION, $dto->type);
        $this->assertEquals('Payment Received', $dto->title);
        $this->assertEquals('Your payment of €100 has been received', $dto->body);
        $this->assertEquals('https://example.com/payment/123', $dto->url);
        $this->assertEquals('https://example.com/images/payment.png', $dto->image);
        $this->assertEquals('https://example.com/icons/payment.png', $dto->icon);
        $this->assertEquals(['transaction_id' => '123', 'amount' => 100], $dto->data);
        $this->assertCount(2, $dto->buttons);
        $this->assertEquals('high', $dto->priority);
        $this->assertEquals(3600, $dto->ttl);
        $this->assertEquals(['user-1', 'user-2'], $dto->recipientIds);
        $this->assertEquals(['topic-1'], $dto->topicIds);
    }

    #[Test]
    public function it_uses_default_values_for_optional_fields(): void
    {
        $data = [
            'title' => 'Simple Notification',
            'body' => 'This is a simple notification',
            'type' => 'system',
        ];

        $dto = PushNotificationDTO::from($data);

        $this->assertNull($dto->notificationId);
        $this->assertEquals(NotificationTypes::SYSTEM, $dto->type);
        $this->assertEquals('Simple Notification', $dto->title);
        $this->assertEquals('This is a simple notification', $dto->body);
        $this->assertNull($dto->url);
        $this->assertNull($dto->image);
        $this->assertNull($dto->icon);
        $this->assertEquals([], $dto->data);
        $this->assertEquals([], $dto->buttons);
        $this->assertEquals('normal', $dto->priority);
        $this->assertEquals(86400, $dto->ttl);
        $this->assertEquals([], $dto->recipientIds);
        $this->assertEquals([], $dto->topicIds);
    }

    #[Test]
    public function it_can_convert_to_array(): void
    {
        $data = [
            'notification_id' => 'notif-789',
            'type' => 'marketing',
            'title' => 'Special Offer',
            'body' => '50% off today only!',
            'url' => 'https://example.com/offers',
            'priority' => 'low',
            'ttl' => 7200,
            'recipient_ids' => ['user-3'],
        ];

        $dto = PushNotificationDTO::from($data);
        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('notif-789', $array['notification_id']);
        $this->assertEquals('marketing', $array['type']);
        $this->assertEquals('Special Offer', $array['title']);
        $this->assertEquals('50% off today only!', $array['body']);
        $this->assertEquals('https://example.com/offers', $array['url']);
        $this->assertEquals('low', $array['priority']);
        $this->assertEquals(7200, $array['ttl']);
        $this->assertEquals(['user-3'], $array['recipient_ids']);
    }

    #[Test]
    public function it_can_create_for_broadcast(): void
    {
        $data = [
            'title' => 'System Maintenance',
            'body' => 'The system will be under maintenance',
            'type' => 'system',
            'topic_ids' => ['all-users'],
        ];

        $dto = PushNotificationDTO::from($data);

        $this->assertEquals([], $dto->recipientIds);
        $this->assertEquals(['all-users'], $dto->topicIds);
        $this->assertEquals(NotificationTypes::SYSTEM, $dto->type);
    }

    #[Test]
    public function it_can_determine_if_scheduled(): void
    {
        $immediateDto = PushNotificationDTO::from([
            'title' => 'Immediate',
            'body' => 'Sent now',
            'type' => 'alert',
        ]);

        $scheduledDto = PushNotificationDTO::from([
            'title' => 'Scheduled',
            'body' => 'Sent later',
            'type' => 'reminder',
            'scheduled_at' => '2025-01-21 10:00:00',
        ]);

        $this->assertFalse($immediateDto->isScheduled());
        $this->assertTrue($scheduledDto->isScheduled());
        $this->assertEquals('2025-01-21 10:00:00', $scheduledDto->scheduledAt);
    }
}
