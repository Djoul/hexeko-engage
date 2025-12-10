<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payments;

use App\Services\Payments\PaymentMethodSelector;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('payments')]
class PaymentMethodSelectorTest extends TestCase
{
    private PaymentMethodSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new PaymentMethodSelector;
    }

    #[Test]
    public function it_returns_balance_only_when_sufficient_funds(): void
    {
        $result = $this->selector->determinePaymentMethod(
            orderAmount: 50.00,
            userBalance: 100.00
        );

        $this->assertEquals('balance', $result->method);
        $this->assertEquals(50.00, $result->balanceAmount);
        $this->assertEquals(0.00, $result->stripeAmount);
    }

    #[Test]
    public function it_returns_mixed_payment_when_partial_balance(): void
    {
        $result = $this->selector->determinePaymentMethod(
            orderAmount: 100.00,
            userBalance: 30.00
        );

        $this->assertEquals('mixed', $result->method);
        $this->assertEquals(30.00, $result->balanceAmount);
        $this->assertEquals(70.00, $result->stripeAmount);
    }

    #[Test]
    public function it_returns_stripe_only_when_no_balance(): void
    {
        $result = $this->selector->determinePaymentMethod(
            orderAmount: 50.00,
            userBalance: 0.00
        );

        $this->assertEquals('stripe', $result->method);
        $this->assertEquals(0.00, $result->balanceAmount);
        $this->assertEquals(50.00, $result->stripeAmount);
    }

    #[Test]
    public function it_returns_balance_when_exact_amount(): void
    {
        $result = $this->selector->determinePaymentMethod(
            orderAmount: 75.50,
            userBalance: 75.50
        );

        $this->assertEquals('balance', $result->method);
        $this->assertEquals(75.50, $result->balanceAmount);
        $this->assertEquals(0.00, $result->stripeAmount);
    }

    #[Test]
    public function it_handles_decimal_precision_correctly(): void
    {
        $result = $this->selector->determinePaymentMethod(
            orderAmount: 99.99,
            userBalance: 100.00
        );

        $this->assertEquals('balance', $result->method);
        $this->assertEquals(99.99, $result->balanceAmount);
        $this->assertEquals(0.00, $result->stripeAmount);
    }

    #[Test]
    public function it_throws_exception_for_negative_order_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->selector->determinePaymentMethod(
            orderAmount: -10.00,
            userBalance: 50.00
        );
    }

    #[Test]
    public function it_handles_negative_balance_as_zero(): void
    {
        $result = $this->selector->determinePaymentMethod(
            orderAmount: 50.00,
            userBalance: -10.00
        );

        $this->assertEquals('stripe', $result->method);
        $this->assertEquals(0.00, $result->balanceAmount);
        $this->assertEquals(50.00, $result->stripeAmount);
    }
}
