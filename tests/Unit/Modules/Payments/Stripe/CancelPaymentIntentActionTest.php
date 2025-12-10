<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments\Stripe;

use App\Integrations\Payments\Stripe\Actions\CancelPaymentIntentAction;
use App\Integrations\Payments\Stripe\Exceptions\StripePaymentException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('stripe')]
class CancelPaymentIntentActionTest extends TestCase
{
    use DatabaseTransactions;

    private CancelPaymentIntentAction $action;

    private MockInterface $stripeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeService = Mockery::mock(StripeService::class);
        $this->action = new CancelPaymentIntentAction($this->stripeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_cancels_payment_intent_successfully(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $paymentIntentId = 'pi_test_cancel';

        $payment = StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_amount' => 500,
            'credit_type' => 'standard',
            'metadata' => [],
        ]);

        $cancelledPaymentIntentData = (object) [
            'id' => $paymentIntentId,
            'amount' => 5000,
            'currency' => 'eur',
            'status' => 'canceled',
            'cancelled_at' => time(),
            'metadata' => [],
        ];

        $this->stripeService
            ->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with($paymentIntentId)
            ->andReturn($cancelledPaymentIntentData);

        // Act
        $result = $this->action->execute([
            'payment_intent_id' => $paymentIntentId,
            'user_id' => $user->id,
        ]);

        // Assert
        $this->assertTrue($result['cancelled']);
        $this->assertInstanceOf(StripePayment::class, $result['payment']);
        $this->assertEquals('cancelled', $result['payment']->status);

        // Verify database update
        $this->assertDatabaseHas('int_stripe_payments', [
            'id' => $payment->id,
            'status' => 'cancelled',
        ]);
    }

    #[Test]
    public function it_throws_exception_for_missing_payment_intent_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment Intent ID is required');

        $this->action->execute([
            'user_id' => 123,
        ]);
    }

    #[Test]
    public function it_throws_exception_for_missing_user_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID is required');

        $this->action->execute([
            'payment_intent_id' => 'pi_test',
        ]);
    }

    #[Test]
    public function it_throws_exception_when_payment_not_found(): void
    {
        $user = ModelFactory::createUser();
        $nonExistentPaymentIntentId = 'pi_nonexistent';

        $this->expectException(StripePaymentException::class);
        $this->expectExceptionMessage("Payment intent {$nonExistentPaymentIntentId} not found or does not belong to user");

        $this->action->execute([
            'payment_intent_id' => $nonExistentPaymentIntentId,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_payment_belongs_to_different_user(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser();
        $user2 = ModelFactory::createUser();
        $paymentIntentId = 'pi_test_other_user';

        StripePayment::create([
            'user_id' => $user1->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_amount' => 500,
            'credit_type' => 'standard',
            'metadata' => [],
        ]);

        $this->expectException(StripePaymentException::class);
        $this->expectExceptionMessage("Payment intent {$paymentIntentId} not found or does not belong to user");

        // Act - User2 tries to cancel User1's payment
        $this->action->execute([
            'payment_intent_id' => $paymentIntentId,
            'user_id' => $user2->id,
        ]);
    }

    #[Test]
    public function it_throws_exception_for_non_cancellable_status(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $paymentIntentId = 'pi_test_completed';

        StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'completed',
            'credit_amount' => 500,
            'credit_type' => 'standard',
            'metadata' => [],
            'processed_at' => now(),
        ]);

        $this->expectException(StripePaymentException::class);
        $this->expectExceptionMessage("Payment intent {$paymentIntentId} cannot be cancelled in current status: completed");

        // Act
        $this->action->execute([
            'payment_intent_id' => $paymentIntentId,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_allows_cancelling_payment_in_pending_status(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $paymentIntentId = 'pi_test_pending';

        StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_amount' => 500,
            'credit_type' => 'standard',
            'metadata' => [],
        ]);

        $this->stripeService
            ->shouldReceive('cancelPaymentIntent')
            ->once()
            ->with($paymentIntentId)
            ->andReturn((object) ['id' => $paymentIntentId, 'status' => 'canceled']);

        // Act
        $result = $this->action->execute([
            'payment_intent_id' => $paymentIntentId,
            'user_id' => $user->id,
        ]);

        // Assert
        $this->assertTrue($result['cancelled']);
        $this->assertEquals('cancelled', $result['payment']->status);
    }
}
