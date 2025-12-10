<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments;

use App\Integrations\Payments\Stripe\Actions\HandleStripeWebhookAction;
use App\Integrations\Payments\Stripe\DTO\WebhookEventDTO;
use App\Integrations\Payments\Stripe\Events\StripePaymentSucceeded;
use App\Integrations\Payments\Stripe\Exceptions\WebhookVerificationException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeWebhookService;
use App\Models\User;
use App\Services\CreditAccountService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

#[Group('stripe')]
#[Group('payments')]
class HandleStripeWebhookActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(StripeWebhookService::class, Mockery::mock(StripeWebhookService::class));
    }

    #[Test]
    public function handles_checkout_session_completed_event(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $checkoutId = 'cs_test_'.uniqid();
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_checkout_id' => $checkoutId,
            'status' => 'pending',
            'credit_amount' => 500,
            'credit_type' => 'voucher_credit',
        ]);

        $paymentIntentId = 'pi_test_'.uniqid();
        $stripeEvent = $this->createStripeEvent('checkout.session.completed', [
            'id' => $checkoutId,
            'payment_intent' => $paymentIntentId,
        ]);

        $this->mockWebhookVerification($stripeEvent);
        $this->mockCreditService($user->id, 'voucher_credit', 500, $paymentIntentId);

        $dto = new WebhookEventDTO(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            secret: 'test_secret'
        );

        // Act
        $action = $this->app->make(HandleStripeWebhookAction::class);
        $action->execute($dto);

        // Assert
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals($paymentIntentId, $payment->stripe_payment_id);
        $this->assertNotNull($payment->processed_at);

        Event::assertDispatched(StripePaymentSucceeded::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id;
        });
    }

    #[Test]
    public function ignores_non_checkout_events(): void
    {
        // Arrange
        Event::fake();

        $stripeEvent = $this->createStripeEvent('customer.created', [
            'id' => 'cus_test_123',
        ]);

        $this->mockWebhookVerification($stripeEvent);

        $dto = new WebhookEventDTO(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            secret: 'test_secret'
        );

        // Act
        $action = $this->app->make(HandleStripeWebhookAction::class);
        $action->execute($dto);

        // Assert
        Event::assertNotDispatched(StripePaymentSucceeded::class);
    }

    /**
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    #[Test]
    public function throws_exception_for_invalid_signature(): void
    {
        // Arrange
        $webhookService = $this->app->make(StripeWebhookService::class);
        $webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->andThrow(new SignatureVerificationException('Invalid signature'));

        $dto = new WebhookEventDTO(
            payload: '{"test": "data"}',
            signature: 'invalid_signature',
            secret: 'test_secret'
        );

        // Act & Assert
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Webhook signature verification failed');

        $action = $this->app->make(HandleStripeWebhookAction::class);
        $action->execute($dto);
    }

    #[Test]
    public function throws_exception_when_payment_not_found(): void
    {
        // Arrange
        $stripeEvent = $this->createStripeEvent('checkout.session.completed', [
            'id' => 'cs_non_existent',
            'payment_intent' => 'pi_test_123',
        ]);

        $this->mockWebhookVerification($stripeEvent);

        $dto = new WebhookEventDTO(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            secret: 'test_secret'
        );

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);

        $action = $this->app->make(HandleStripeWebhookAction::class);
        $action->execute($dto);
    }

    #[Test]
    public function adds_credits_with_correct_parameters(): void
    {
        // Arrange
        $user = User::factory()->create();
        $checkoutId = 'cs_test_credits_'.uniqid();
        StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_checkout_id' => $checkoutId,
            'status' => 'pending',
            'credit_amount' => 1000,
            'credit_type' => 'premium_credit',
        ]);

        $paymentIntentId = 'pi_premium_'.uniqid();
        $stripeEvent = $this->createStripeEvent('checkout.session.completed', [
            'id' => $checkoutId,
            'payment_intent' => $paymentIntentId,
        ]);

        $this->mockWebhookVerification($stripeEvent);

        // Verify CreditAccountService is called with correct parameters
        $creditService = Mockery::mock(CreditAccountService::class);
        $creditService->shouldReceive('addCredit')
            ->once()
            ->with(
                'user',
                $user->id,
                'premium_credit',
                1000,
                "Stripe payment: {$paymentIntentId}"
            );

        $this->app->instance(CreditAccountService::class, $creditService);

        $dto = new WebhookEventDTO(
            payload: '{"test": "data"}',
            signature: 'test_signature',
            secret: 'test_secret'
        );

        // Act
        $action = $this->app->make(HandleStripeWebhookAction::class);
        $action->execute($dto);

        // Assert - Verify that the mock expectations were met
        $this->addToAssertionCount(1);
    }

    private function createStripeEvent(string $type, array $data): \Stripe\Event
    {
        // Create a real Stripe Event object using the constructor
        $event = \Stripe\Event::constructFrom([
            'id' => 'evt_test_'.uniqid(),
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $data],
            'created' => time(),
            'livemode' => false,
        ]);

        return $event;
    }

    private function mockWebhookVerification(\Stripe\Event $event): void
    {
        $webhookService = $this->app->make(StripeWebhookService::class);
        $webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->andReturn($event);
    }

    private function mockCreditService(string $userId, string $creditType, int $amount, string $paymentId): void
    {
        $creditService = Mockery::mock(CreditAccountService::class);
        $creditService->shouldReceive('addCredit')
            ->once()
            ->with(
                'user',
                $userId,
                $creditType,
                $amount,
                "Stripe payment: {$paymentId}"
            );

        $this->app->instance(CreditAccountService::class, $creditService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
