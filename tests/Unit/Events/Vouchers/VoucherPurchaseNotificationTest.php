<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Vouchers;

use App\Events\Vouchers\VoucherPurchaseNotification;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('vouchers')]
#[Group('amilon')]
#[Group('notification')]
class VoucherPurchaseNotificationTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $userId = '123';
        $event = new VoucherPurchaseNotification(
            userId: $userId,
            orderId: '456',
            status: 'completed',
            orderData: ['test' => 'data'],
            message: 'Test message'
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_event_name(): void
    {
        $event = new VoucherPurchaseNotification(
            userId: '123',
            orderId: '456',
            status: 'completed',
            orderData: ['test' => 'data'],
            message: 'Test message'
        );

        $this->assertEquals('voucher.purchase.completed', $event->broadcastAs());
    }

    #[Test]
    public function it_broadcasts_with_correct_data(): void
    {
        $orderId = '456';
        $status = 'completed';
        $orderData = ['product_name' => 'Test Product', 'amount' => 100];
        $message = 'Test message';

        $event = new VoucherPurchaseNotification(
            userId: '123',
            orderId: $orderId,
            status: $status,
            orderData: $orderData,
            message: $message
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('orderId', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('message', $broadcastData);
        $this->assertArrayHasKey('orderData', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);

        $this->assertEquals($orderId, $broadcastData['orderId']);
        $this->assertEquals($status, $broadcastData['status']);
        $this->assertEquals($message, $broadcastData['message']);
        $this->assertEquals($orderData, $broadcastData['orderData']);
    }

    #[Test]
    public function it_supports_different_statuses(): void
    {
        $statuses = ['created', 'pending_stripe_payment', 'completed', 'vouchers_received'];

        foreach ($statuses as $status) {
            $event = new VoucherPurchaseNotification(
                userId: '123',
                orderId: '456',
                status: $status,
                orderData: [],
                message: null
            );

            $this->assertEquals('voucher.purchase.'.$status, $event->broadcastAs());
        }
    }
}
