<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Payments\Stripe\Actions;

use App\Integrations\Payments\Stripe\Actions\HandleStripeWebhookAction;
use App\Integrations\Payments\Stripe\DTO\WebhookEventDTO;
use App\Integrations\Payments\Stripe\Events\StripePaymentFailed;
use App\Integrations\Payments\Stripe\Events\StripePaymentSucceeded;
use App\Integrations\Payments\Stripe\Exceptions\WebhookVerificationException;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeWebhookService;
use App\Services\CreditAccountService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Exception\SignatureVerificationException;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('stripe')]
#[Group('payments')]
class HandleStripeWebhookActionTest extends TestCase
{
    private HandleStripeWebhookAction $action;

    private MockInterface $creditAccountService;

    private MockInterface $webhookService;

    private string $webhookSecret = 'whsec_test_secret'; // pragma: allowlist secret

    protected function setUp(): void
    {
        parent::setUp();

        $this->creditAccountService = Mockery::mock(CreditAccountService::class);
        $this->app->instance(CreditAccountService::class, $this->creditAccountService);

        $this->webhookService = Mockery::mock(StripeWebhookService::class);

        $this->action = new HandleStripeWebhookAction($this->webhookService);

        Event::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_handles_payment_intent_succeeded_event(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'stripe-webhook-test-'.uniqid().'@example.com',
        ]);

        $paymentId = 'pi_test_'.uniqid();
        $payment = StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentId,
            'status' => 'pending',
            'amount' => 100.00,
            'currency' => 'eur',
            'credit_amount' => 1000,
            'credit_type' => 'standard',
            'metadata' => [],
        ]);

        $eventData = [
            'id' => 'evt_test_webhook',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $paymentId,
                    'amount' => 10000,
                    'currency' => 'eur',
                    'status' => 'succeeded',
                    'metadata' => [
                        'user_id' => (string) $user->id,
                    ],
                ],
            ],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        // Mock Stripe Webhook
        $stripeEvent = \Stripe\Event::constructFrom($eventData);
        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $this->webhookSecret
        );

        $this->creditAccountService
            ->shouldReceive('addCredit')
            ->once()
            ->withAnyArgs();

        // Act
        $this->action->execute($dto);

        // Assert
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->processed_at);

        Event::assertDispatched(StripePaymentSucceeded::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id;
        });
    }

    #[Test]
    public function it_handles_payment_intent_failed_event(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'stripe-failed-test-'.uniqid().'@example.com',
        ]);

        $paymentId = 'pi_test_failed_'.uniqid();
        $payment = StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentId,
            'status' => 'pending',
            'amount' => 50.00,
            'currency' => 'eur',
            'credit_amount' => 500,
            'credit_type' => 'premium',
            'metadata' => [],
        ]);

        $eventData = [
            'id' => 'evt_test_failed',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => $paymentId,
                    'amount' => 5000,
                    'currency' => 'eur',
                    'status' => 'requires_payment_method',
                    'last_payment_error' => [
                        'message' => 'Your card was declined',
                    ],
                ],
            ],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        // Mock Stripe Webhook
        $stripeEvent = \Stripe\Event::constructFrom($eventData);
        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $this->webhookSecret
        );

        // Act
        $this->action->execute($dto);

        // Assert
        $payment->refresh();
        $this->assertEquals('failed', $payment->status);

        Event::assertDispatched(StripePaymentFailed::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id;
        });

        // Ensure no credits were added
        $this->creditAccountService->shouldNotHaveReceived('addCredit');
    }

    #[Test]
    public function it_handles_checkout_session_completed_event(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'checkout-test-'.uniqid().'@example.com',
        ]);

        $checkoutId = 'cs_test_'.uniqid();
        $payment = StripePayment::create([
            'user_id' => $user->id,
            'stripe_checkout_id' => $checkoutId,
            'status' => 'pending',
            'amount' => 75.00,
            'currency' => 'eur',
            'credit_amount' => 750,
            'credit_type' => 'bonus',
            'metadata' => [],
        ]);

        $paymentIntentId = 'pi_test_from_checkout_'.uniqid();
        $eventData = [
            'id' => 'evt_test_checkout',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $checkoutId,
                    'payment_intent' => $paymentIntentId,
                    'amount_total' => 7500,
                    'currency' => 'eur',
                    'status' => 'complete',
                ],
            ],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        // Mock Stripe Webhook
        $stripeEvent = \Stripe\Event::constructFrom($eventData);
        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $this->webhookSecret
        );

        $this->creditAccountService
            ->shouldReceive('addCredit')
            ->once()
            ->with(
                'user',
                $user->id,
                'bonus',
                750,
                'Stripe payment: '.$paymentIntentId
            );

        // Act
        $this->action->execute($dto);

        // Assert
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals($paymentIntentId, $payment->stripe_payment_id);

        Event::assertDispatched(StripePaymentSucceeded::class);
    }

    #[Test]
    public function it_throws_exception_on_invalid_signature(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'payment_intent.succeeded']);
        $invalidSignature = 'invalid_signature';

        // The webhook service should never be called for invalid signatures
        $this->webhookService
            ->shouldNotReceive('constructEvent');

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $invalidSignature,
            secret: $this->webhookSecret
        );

        // Act & Assert
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Webhook signature has invalid format');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_when_signature_is_empty(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'checkout.session.completed']);
        $emptySignature = '';

        // The webhook service should never be called for empty signatures
        $this->webhookService
            ->shouldNotReceive('constructEvent');

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $emptySignature,
            secret: $this->webhookSecret
        );

        // Act & Assert
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Webhook signature is missing');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_when_signature_format_is_malformed(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'payment_intent.payment_failed']);
        $malformedSignature = 'malformed_sig_without_proper_format';

        // The webhook service should never be called for malformed signatures
        $this->webhookService
            ->shouldNotReceive('constructEvent');

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $malformedSignature,
            secret: $this->webhookSecret
        );

        // Act & Assert
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Webhook signature has invalid format');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_when_webhook_secret_is_incorrect(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'checkout.session.completed']);
        $signature = 't=1234567890,v1=signature_here';
        $wrongSecret = 'wrong_secret_key'; // pragma: allowlist secret

        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $wrongSecret)
            ->andThrow(new SignatureVerificationException('No signatures found matching the expected signature for payload'));

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $wrongSecret
        );

        // Act & Assert
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Webhook signature verification failed');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_when_timestamp_is_too_old(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'payment_intent.succeeded']);
        $oldTimestampSignature = 't=1000000000,v1=old_signature';

        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $oldTimestampSignature, $this->webhookSecret)
            ->andThrow(new SignatureVerificationException('Timestamp outside the tolerance zone'));

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $oldTimestampSignature,
            secret: $this->webhookSecret
        );

        // Act & Assert
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Webhook signature verification failed');

        $this->action->execute($dto);
    }

    #[Test]
    public function it_preserves_original_stripe_exception_in_webhook_verification_exception(): void
    {
        // Arrange
        $payload = json_encode(['type' => 'test.event']);
        // Use a valid signature format to bypass format validation
        $signature = 't=1234567890,v1=some_signature';

        $originalException = new SignatureVerificationException('Original Stripe error message');

        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andThrow($originalException);

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $this->webhookSecret
        );

        // Act
        try {
            $this->action->execute($dto);
            $this->fail('Expected WebhookVerificationException was not thrown');
        } catch (WebhookVerificationException $e) {
            // Assert
            $this->assertSame($originalException, $e->getPrevious());
            $this->assertEquals('Original Stripe error message', $e->getPrevious()->getMessage());
            $this->assertEquals('Webhook signature verification failed', $e->getMessage());
        }
    }

    #[Test]
    public function it_logs_unhandled_event_types(): void
    {
        // Arrange
        $eventData = [
            'id' => 'evt_test_unhandled',
            'type' => 'customer.created',
            'data' => ['object' => []],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        // Mock Stripe Webhook
        $stripeEvent = \Stripe\Event::constructFrom($eventData);
        $this->webhookService
            ->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);

        $dto = new WebhookEventDTO(
            payload: $payload,
            signature: $signature,
            secret: $this->webhookSecret
        );

        // Act
        $this->action->execute($dto);

        // Assert - No exception should be thrown
        $this->assertTrue(true);
    }

    /**
     * Generate a valid Stripe webhook signature for testing
     */
    private function generateWebhookSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }
}
