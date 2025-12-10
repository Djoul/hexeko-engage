<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Vouchers;

use App\Events\Vouchers\VoucherPurchaseError;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('vouchers')]
#[Group('amilon')]
class VoucherPurchaseErrorTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_private_channel(): void
    {
        $userId = '123';
        $event = new VoucherPurchaseError(
            userId: $userId,
            errorCode: 'PAYMENT_FAILED',
            errorMessage: 'Insufficient funds',
            context: ['order_id' => '456']
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.'.$userId, $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_event_name(): void
    {
        $event = new VoucherPurchaseError(
            userId: '123',
            errorCode: 'PAYMENT_FAILED',
            errorMessage: 'Insufficient funds',
            context: null
        );

        $this->assertEquals('voucher.purchase.error', $event->broadcastAs());
    }

    #[Test]
    public function it_broadcasts_with_correct_data(): void
    {
        $errorCode = 'AMILON_ORDER_FAILED';
        $errorMessage = 'Failed to create order with Amilon';
        $context = ['product_code' => 'VOUCHER_100', 'external_order_id' => 'ORD-123'];

        $event = new VoucherPurchaseError(
            userId: '123',
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            context: $context
        );

        $broadcastData = $event->broadcastWith();

        $this->assertArrayHasKey('errorCode', $broadcastData);
        $this->assertArrayHasKey('errorMessage', $broadcastData);
        $this->assertArrayHasKey('context', $broadcastData);
        $this->assertArrayHasKey('timestamp', $broadcastData);

        $this->assertEquals($errorCode, $broadcastData['errorCode']);
        $this->assertEquals($errorMessage, $broadcastData['errorMessage']);
        $this->assertEquals($context, $broadcastData['context']);
    }

    #[Test]
    public function it_handles_null_context(): void
    {
        $event = new VoucherPurchaseError(
            userId: '123',
            errorCode: 'GENERIC_ERROR',
            errorMessage: 'An unexpected error occurred',
            context: null
        );

        $broadcastData = $event->broadcastWith();

        $this->assertNull($broadcastData['context']);
    }

    #[Test]
    public function it_supports_different_error_codes(): void
    {
        $errorCodes = [
            'PAYMENT_FAILED' => 'Payment processing failed',
            'INSUFFICIENT_BALANCE' => 'Insufficient balance',
            'AMILON_ORDER_FAILED' => 'Failed to create Amilon order',
            'STRIPE_ERROR' => 'Stripe payment error',
        ];

        foreach ($errorCodes as $code => $message) {
            $event = new VoucherPurchaseError(
                userId: '123',
                errorCode: $code,
                errorMessage: $message,
                context: null
            );

            $broadcastData = $event->broadcastWith();

            $this->assertEquals($code, $broadcastData['errorCode']);
            $this->assertEquals($message, $broadcastData['errorMessage']);
        }
    }
}
