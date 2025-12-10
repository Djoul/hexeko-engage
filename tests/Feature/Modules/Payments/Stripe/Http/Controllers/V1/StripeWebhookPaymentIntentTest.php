<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

use App\Integrations\Payments\Stripe\Events\StripePaymentFailed;
use App\Integrations\Payments\Stripe\Events\StripePaymentSucceeded;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeWebhookService;
use App\Models\User;
use App\Services\CreditAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('stripe')]
#[Group('payments')]
#[Group('webhook')]
class StripeWebhookPaymentIntentTest extends TestCase
{
    use DatabaseTransactions;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
        config(['services.stripe.webhook_secret_cli' => null]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function webhook_handles_payment_intent_succeeded_complete_flow(): void
    {

        // Arrange - Simulate complete payment flow
        Event::fake();

        // 1. Create user who will make the payment
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // 2. Create payment intent ID (normally created by Stripe API)
        $paymentIntentId = 'pi_test_'.time().'_'.mt_rand(1000, 9999);

        // 3. Create payment record (simulating what CreatePaymentIntentAction does)
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'stripe_checkout_id' => null, // Direct payment, no checkout session
            'status' => 'pending',
            'amount' => 10000,
            'currency' => 'eur',
            'credit_amount' => 1000,
            'credit_type' => 'standard',
            'metadata' => [
                'product_id' => 'prod_'.uniqid(),
                'product_name' => 'Premium Credits',
                'purchase_reference' => 'REF-'.uniqid(),
            ],
        ]);

        // 4. Create realistic Stripe webhook event data
        $eventData = [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'api_version' => '2023-10-16',
            'created' => time(),
            'type' => 'payment_intent.succeeded',
            'livemode' => false,
            'pending_webhooks' => 1,
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'amount' => 10000, // Amount in cents
                    'amount_received' => 10000,
                    'currency' => 'eur',
                    'status' => 'succeeded',
                    'charges' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'ch_'.uniqid(),
                                'object' => 'charge',
                                'amount' => 10000,
                                'currency' => 'eur',
                                'paid' => true,
                                'status' => 'succeeded',
                                'receipt_url' => 'https://pay.stripe.com/receipts/'.uniqid(),
                            ],
                        ],
                    ],
                    'metadata' => $payment->metadata,
                    'payment_method' => 'pm_'.uniqid(),
                    'payment_method_types' => ['card'],
                    'receipt_email' => $user->email,
                ],
            ],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        // 5. Create fake Stripe Event
        $stripeEvent = \Stripe\Event::constructFrom($eventData);

        // 6. Mock StripeWebhookService
        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        // 7. Mock CreditAccountService - bind as singleton
        $creditServiceMock = Mockery::mock(CreditAccountService::class);
        $creditServiceMock->shouldReceive('addCredit')
            ->once()
            ->with(
                User::class,
                $user->id,
                'cash',  // CreditTypes::CASH resolves to 'cash'
                10000,   // The full payment amount is passed, not credit_amount
                'Stripe payment: '.$paymentIntentId
            );
        $this->app->singleton(CreditAccountService::class, function () use ($creditServiceMock) {
            return $creditServiceMock;
        });

        // Act - Send as raw body
        $response = $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // Assert
        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->processed_at);

        Event::assertDispatched(StripePaymentSucceeded::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id
                && $event->payment->status === 'completed';
        });
    }

    #[Test]
    public function webhook_handles_payment_intent_failed_complete_flow(): void
    {
        // Arrange
        Event::fake();

        $user = User::factory()->create();
        $paymentIntentId = 'pi_test_failed_'.time().'_'.mt_rand(1000, 9999);

        // Create payment that will fail
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'status' => 'pending',
            'amount' => 50.00,
            'currency' => 'eur',
            'credit_amount' => 500,
            'credit_type' => 'premium',
        ]);

        // Create failed payment event
        $eventData = [
            'id' => 'evt_'.uniqid(),
            'object' => 'event',
            'api_version' => '2023-10-16',
            'created' => time(),
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'amount' => 5000,
                    'currency' => 'eur',
                    'status' => 'requires_payment_method',
                    'last_payment_error' => [
                        'code' => 'card_declined',
                        'decline_code' => 'insufficient_funds',
                        'message' => 'Your card has insufficient funds.',
                        'type' => 'card_error',
                    ],
                ],
            ],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        $stripeEvent = \Stripe\Event::constructFrom($eventData);

        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        // Credit service should NOT be called for failed payments
        $creditServiceMock = Mockery::mock(CreditAccountService::class);
        $creditServiceMock->shouldNotReceive('addCredit');
        $this->app->singleton(CreditAccountService::class, function () use ($creditServiceMock) {
            return $creditServiceMock;
        });

        // Act - Send as raw body
        $response = $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // Assert
        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $payment->refresh();
        $this->assertEquals('failed', $payment->status);
        $this->assertArrayHasKey('error', $payment->metadata);
        $this->assertEquals('Your card has insufficient funds.', $payment->metadata['error']);

        Event::assertDispatched(StripePaymentFailed::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id
                && $event->payment->status === 'failed';
        });

        Event::assertNotDispatched(StripePaymentSucceeded::class);
    }

    private function generateWebhookSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
