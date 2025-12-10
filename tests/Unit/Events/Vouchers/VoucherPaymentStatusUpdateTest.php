<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Vouchers;

use App\Events\Vouchers\VoucherPaymentStatusUpdate;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('vouchers')]
#[Group('amilon')]
class VoucherPaymentStatusUpdateTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $userId = '123';
        $event = new VoucherPaymentStatusUpdate(
            userId: $userId,
            paymentId: '456',
            status: 'completed',
            paymentMethod: 'stripe',
            metadata: ['test' => 'data']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_event_name(): void
    {
        $event = new VoucherPaymentStatusUpdate(
            userId: '123',
            paymentId: '456',
            status: 'completed',
            paymentMethod: 'stripe',
            metadata: null
        );

        $this->assertEquals('voucher.payment.status', $event->broadcastAs());
    }

    #[Test]
    public function it_broadcasts_with_correct_data(): void
    {
        $paymentId = '456';
        $status = 'completed';
        $paymentMethod = 'stripe';
        $metadata = ['stripe_payment_intent_id' => 'pi_test_123', 'amount' => 2500];

        $event = new VoucherPaymentStatusUpdate(
            userId: '123',
            paymentId: $paymentId,
            status: $status,
            paymentMethod: $paymentMethod,
            metadata: $metadata
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('paymentId', $broadcastData);
        $this->assertArrayHasKey('status', $broadcastData);
        $this->assertArrayHasKey('paymentMethod', $broadcastData);
        $this->assertArrayHasKey('metadata', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);

        $this->assertEquals($paymentId, $broadcastData['paymentId']);
        $this->assertEquals($status, $broadcastData['status']);
        $this->assertEquals($paymentMethod, $broadcastData['paymentMethod']);
        $this->assertEquals($metadata, $broadcastData['metadata']);
    }

    #[Test]
    public function it_handles_null_metadata(): void
    {
        $event = new VoucherPaymentStatusUpdate(
            userId: '123',
            paymentId: '456',
            status: 'failed',
            paymentMethod: 'balance',
            metadata: null
        );

        $broadcastData = $event->broadcastWith();

        $this->assertNull($broadcastData['metadata']);
    }
}
