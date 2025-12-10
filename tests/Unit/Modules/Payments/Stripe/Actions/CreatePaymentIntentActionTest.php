<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments\Stripe\Actions;

use App\Integrations\Payments\Stripe\Actions\CreatePaymentIntentAction;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('stripe')]
class CreatePaymentIntentActionTest extends TestCase
{
    private MockInterface $stripeService;

    private CreatePaymentIntentAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeService = Mockery::mock(StripeService::class);
        $this->app->instance(StripeService::class, $this->stripeService);

        $this->action = new CreatePaymentIntentAction($this->stripeService);
    }

    #[Test]
    public function it_creates_payment_intent_and_stripe_payment_in_transaction(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'stripe-test-'.uniqid().'@example.com',
        ]);

        $amount = 100.00;
        $paymentIntentId = 'pi_test_'.uniqid();

        $paymentIntentData = [
            'id' => $paymentIntentId,
            'amount' => 10000, // in cents
            'currency' => 'eur',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_secret_xyz',
        ];

        $metadata = [
            'user_id' => (string) $user->id,
            'custom_field' => 'custom_value',
        ];

        $this->stripeService->shouldReceive('createPaymentIntent')
            ->once()
            ->with($amount / 100, $metadata)
            ->andReturn((object) $paymentIntentData);

        $data = [
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'eur',
            'metadata' => $metadata,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('client_secret', $result);
        $this->assertArrayHasKey('payment_intent_id', $result);

        $payment = $result['payment'];
        $this->assertInstanceOf(StripePayment::class, $payment);
        $this->assertEquals($paymentIntentId, $payment->stripe_payment_id);
        $this->assertEquals($amount, $payment->amount);
        $this->assertEquals('pending', $payment->status);
        $this->assertEquals('eur', $payment->currency);
        $this->assertEquals($metadata, $payment->metadata);

        // Verify database record was created
        $this->assertDatabaseHas('int_stripe_payments', [
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => $amount,
            'currency' => 'eur',
            'status' => 'pending',
        ]);

        // Verify only one payment was created
        $this->assertEquals(1, StripePayment::where('stripe_payment_id', $paymentIntentId)->count());
    }

    #[Test]
    public function it_validates_positive_amount(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        $data = [
            'user_id' => $user->id,
            'amount' => -50.00, // Negative amount
            'currency' => 'eur',
        ];

        // Assert
        $this->expectException(StripePaymentException::class);
        $this->expectExceptionMessage('Amount must be greater than 0');

        // Act
        $this->action->execute($data);
    }

    #[Test]
    public function it_rolls_back_transaction_on_stripe_error(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $amount = 100.00;

        $this->stripeService->shouldReceive('createPaymentIntent')
            ->once()
            ->andThrow(new StripePaymentException('Stripe API error'));

        $data = [
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'eur',
        ];

        $initialCount = StripePayment::count();

        // Act & Assert
        try {
            $this->action->execute($data);
            $this->fail('Expected StripePaymentException was not thrown');
        } catch (StripePaymentException $e) {
            $this->assertEquals('Stripe API error', $e->getMessage());

            // Verify no payment was created due to rollback
            $this->assertEquals($initialCount, StripePayment::count());
        }
    }

    #[Test]
    public function it_uses_default_currency_when_not_provided(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $amount = 75.00;
        $paymentIntentId = 'pi_test_default_'.uniqid();

        $paymentIntentData = [
            'id' => $paymentIntentId,
            'amount' => 7500,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_secret_abc',
        ];

        $this->stripeService->shouldReceive('createPaymentIntent')
            ->once()
            ->with($amount / 100, Mockery::on(function (array $metadata) use ($user): bool {
                return $metadata['user_id'] === (string) $user->id;
            }))
            ->andReturn((object) $paymentIntentData);

        $data = [
            'user_id' => $user->id,
            'amount' => $amount,
            // No currency provided
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $payment = $result['payment'];
        $this->assertEquals('eur', $payment->currency);
    }

    #[Test]
    public function it_stores_credit_information_when_provided(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
        ]);
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $amount = 50.00;
        $creditAmount = 500; // 500 credits
        $creditType = 'standard_credit';
        $paymentIntentId = 'pi_test_credit_'.uniqid();

        $paymentIntentData = [
            'id' => $paymentIntentId,
            'amount' => 5000,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_secret_credit',
        ];

        $this->stripeService->shouldReceive('createPaymentIntent')
            ->once()
            ->with($amount / 100, Mockery::on(function (array $metadata) use ($user): bool {
                return $metadata['user_id'] === (string) $user->id;
            }))
            ->andReturn((object) $paymentIntentData);

        $data = [
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => 'eur',
            'credit_amount' => $creditAmount,
            'credit_type' => $creditType,
        ];

        // Act
        $result = $this->action->execute($data);

        // Assert
        $payment = $result['payment'];
        $this->assertEquals($creditAmount, $payment->credit_amount);
        $this->assertEquals('cash', $payment->credit_type);

        $this->assertDatabaseHas('int_stripe_payments', [
            'stripe_payment_id' => $paymentIntentId,
            'credit_amount' => $creditAmount,
            'credit_type' => 'cash',
        ]);
    }

    #[Test]
    public function it_requires_user_id_for_payment_processing(): void
    {
        // Arrange
        $amount = 200.00;

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID is required for payment processing');

        $this->action->execute([
            'amount' => $amount,
            'metadata' => [
                'type' => 'purchase',
            ],
        ]);
    }
}
