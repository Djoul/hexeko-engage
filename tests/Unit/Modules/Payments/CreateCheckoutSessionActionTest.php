<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments;

use App\Integrations\Payments\Stripe\Actions\CreateCheckoutSessionAction;
use App\Integrations\Payments\Stripe\DTO\CheckoutSessionDTO;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use App\Models\User;
use DB;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('stripe')]
class CreateCheckoutSessionActionTest extends TestCase
{
    protected function setUp(): void
    {

        parent::setUp();

    }

    #[Test]
    public function creates_payment_record_and_returns_checkout_data(): void
    {
        // Arrange
        $user = User::factory()->create();

        $sessionId = 'cs_test_creates_payment_'.uniqid();
        $stripeSession = [
            'id' => $sessionId,
            'url' => 'https://checkout.stripe.com/123',
        ];

        /** @var MockInterface&StripeService $stripeService */
        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($stripeSession);

        $this->app->instance(StripeService::class, $stripeService);

        $dto = new CheckoutSessionDTO(
            userId: (string) $user->id,
            amount: 5000,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act
        $action = new CreateCheckoutSessionAction($stripeService);
        $result = $action->execute($dto);

        // Assert
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertEquals('https://checkout.stripe.com/123', $result['checkout_url']);
        $this->assertEquals($sessionId, $result['session_id']);
        $this->assertInstanceOf(StripePayment::class, $result['payment']);

        // Verify payment was created
        $this->assertDatabaseHas('int_stripe_payments', [
            'user_id' => $user->id,
            'stripe_checkout_id' => $sessionId,
            'status' => 'pending',
            'amount' => 5000,
            'currency' => 'eur',
            'credit_amount' => 500,
            'credit_type' => 'voucher_credit',
        ]);
    }

    #[Test]
    public function stores_product_name_in_metadata(): void
    {
        // Arrange
        $user = User::factory()->create();

        $sessionId = 'cs_test_metadata_'.uniqid();
        $stripeSession = [
            'id' => $sessionId,
            'url' => 'https://checkout.stripe.com/123',
        ];

        /** @var MockInterface&StripeService $stripeService */
        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($stripeSession);

        $this->app->instance(StripeService::class, $stripeService);

        $dto = new CheckoutSessionDTO(
            userId: (string) $user->id,
            amount: 5000,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Premium Credits'
        );

        // Act
        $action = new CreateCheckoutSessionAction($stripeService);
        $result = $action->execute($dto);

        // Assert
        $payment = $result['payment'];
        $this->assertEquals(['product_name' => 'Premium Credits'], $payment->metadata);
    }

    #[Test]
    public function throws_exception_when_stripe_service_fails(): void
    {
        // Arrange
        $user = User::factory()->create();

        /** @var MockInterface&StripeService $stripeService */
        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow(new Exception('Stripe API error'));

        $this->app->instance(StripeService::class, $stripeService);

        $dto = new CheckoutSessionDTO(
            userId: (string) $user->id,
            amount: 5000,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act & Assert
        $this->expectException(StripePaymentException::class);
        $this->expectExceptionMessage('Unable to create payment session');

        $action = new CreateCheckoutSessionAction($stripeService);
        $action->execute($dto);
    }

    #[Test]
    public function rolls_back_transaction_on_failure(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Count existing payments before the test
        $initialPaymentCount = DB::table('int_stripe_payments')->count();

        // Create a StripeService mock that will throw an exception on the second call
        /** @var MockInterface&StripeService $stripeService */
        $stripeService = Mockery::mock(StripeService::class);
        $stripeService->shouldReceive('createCheckoutSession')
            ->once()
            ->andThrow(new Exception('Stripe API error'));

        $this->app->instance(StripeService::class, $stripeService);

        $dto = new CheckoutSessionDTO(
            userId: (string) $user->id,
            amount: 5000,
            currency: 'eur',
            creditType: 'voucher_credit',
            creditAmount: 500,
            successUrl: 'https://app.test/success',
            cancelUrl: 'https://app.test/cancel',
            productName: 'Test Credits'
        );

        // Act & Assert
        $this->expectException(StripePaymentException::class);

        $action = new CreateCheckoutSessionAction($stripeService);

        try {
            $action->execute($dto);
        } catch (StripePaymentException $e) {
            // Verify no new payment was created due to rollback
            $finalPaymentCount = DB::table('int_stripe_payments')->count();
            $this->assertEquals($initialPaymentCount, $finalPaymentCount, 'No new payment should have been created due to transaction rollback');
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
