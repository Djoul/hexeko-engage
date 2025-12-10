<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

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
use Stripe\Exception\SignatureVerificationException;
use Tests\TestCase;

#[Group('stripe')]
#[Group('payments')]
#[Group('webhook')]
class StripeWebhookTest extends TestCase
{
    use DatabaseTransactions;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
        // Ensure CLI secret is not set for tests
        config(['services.stripe.webhook_secret_cli' => null]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function webhook_handles_checkout_session_completed(): void
    {
        // Arrange - Create a complete flow
        Event::fake();

        // 1. Create user
        $user = User::factory()->create();

        // 2. Create payment intent ID and checkout session ID
        $checkoutId = 'cs_test_'.time().'_'.mt_rand(1000, 9999);
        $paymentIntentId = 'pi_test_'.time().'_'.mt_rand(1000, 9999);

        // 3. Create pending payment (simulating what happens when checkout session is created)
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_checkout_id' => $checkoutId,
            'stripe_payment_id' => null, // Not set yet, will be set by webhook
            'status' => 'pending',
            'amount' => 50.00,
            'currency' => 'eur',
            'credit_amount' => 500,
            'credit_type' => 'voucher_credit',
            'metadata' => [
                'product_name' => 'Test Product',
                'order_reference' => 'ORD-'.uniqid(),
            ],
        ]);

        // Verify payment was created
        $this->assertDatabaseHas('int_stripe_payments', [
            'id' => $payment->id,
            'stripe_checkout_id' => $checkoutId,
            'status' => 'pending',
        ]);

        // 4. Create fake Stripe webhook event data
        $eventData = [
            'id' => 'evt_test_'.time().'_'.mt_rand(1000, 9999),
            'object' => 'event',
            'api_version' => '2023-10-16',
            'created' => time(),
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $checkoutId,
                    'object' => 'checkout.session',
                    'payment_intent' => $paymentIntentId,
                    'payment_status' => 'paid',
                    'status' => 'complete',
                    'mode' => 'payment',
                    'amount_total' => 5000, // in cents
                    'currency' => 'eur',
                    'customer_details' => [
                        'email' => $user->email,
                    ],
                    'metadata' => $payment->metadata,
                ],
            ],
        ];

        $payload = json_encode($eventData);
        $signature = $this->generateWebhookSignature($payload);

        // 5. Create fake Stripe Event object
        $stripeEvent = \Stripe\Event::constructFrom($eventData);

        // 6. Mock StripeWebhookService
        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        // 7. Mock CreditAccountService - MUST bind as singleton to ensure same instance
        $creditServiceMock = Mockery::mock(CreditAccountService::class);
        $creditServiceMock->shouldReceive('addCredit')
            ->once()
            ->with(
                'user',
                $user->id,
                'voucher_credit',
                500,
                'Stripe payment: '.$paymentIntentId
            );
        // Bind as singleton to ensure app() returns the same mock instance
        $this->app->singleton(CreditAccountService::class, function () use ($creditServiceMock) {
            return $creditServiceMock;
        });

        // Act - Send webhook request as raw body
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

        // Check if response has error
        $content = $response->json();
        if (isset($content['error'])) {
            $this->fail('Webhook returned error: '.$content['error']);
        }

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals($paymentIntentId, $payment->stripe_payment_id);
        $this->assertNotNull($payment->processed_at);

        Event::assertDispatched(StripePaymentSucceeded::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id;
        });
    }

    #[Test]
    public function webhook_fails_with_invalid_signature(): void
    {
        $payload = $this->createWebhookPayload('checkout.session.completed', [
            'id' => 'cs_test_invalid_sig',
        ]);

        // Use a properly formatted but invalid signature
        $invalidSignature = 't=1234567890,v1=invalid_signature_hash';

        // Mock StripeWebhookService to throw exception for invalid signature
        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $invalidSignature, $this->webhookSecret)
            ->andThrow(new SignatureVerificationException('Invalid signature'));
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $invalidSignature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertStatus(400);
    }

    #[Test]
    public function webhook_fails_without_signature(): void
    {
        $payload = $this->createWebhookPayload('checkout.session.completed', [
            'id' => 'cs_test_no_sig',
        ]);

        // No mock needed - empty signature should be caught before calling the service

        $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertStatus(400);
    }

    #[Test]
    public function webhook_ignores_unhandled_event_types(): void
    {
        $payload = $this->createWebhookPayload('customer.created', [
            'id' => 'cus_test_123',
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Mock StripeWebhookService to return a valid event
        $stripeEvent = \Stripe\Event::constructFrom(json_decode($payload, true));
        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertOk();
    }

    #[Test]
    public function webhook_fails_for_non_existent_payment(): void
    {
        $payload = $this->createWebhookPayload('checkout.session.completed', [
            'id' => 'cs_non_existent',
            'payment_intent' => 'pi_test_123',
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Mock StripeWebhookService to return a valid event
        $stripeEvent = \Stripe\Event::constructFrom(json_decode($payload, true));
        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertStatus(404);
    }

    #[Test]
    public function webhook_adds_credits_on_successful_payment(): void
    {
        // Arrange
        $user = User::factory()->create();

        $checkoutId = 'cs_test_'.time().'_'.mt_rand(1000, 9999);
        $paymentIntentId = 'pi_test_'.time().'_'.mt_rand(1000, 9999);
        StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_checkout_id' => $checkoutId,
            'status' => 'pending',
            'amount' => 50.00,
            'currency' => 'eur',
            'credit_amount' => 500,
            'credit_type' => 'voucher_credit',
        ]);

        $payload = $this->createWebhookPayload('checkout.session.completed', [
            'id' => $checkoutId,
            'payment_intent' => $paymentIntentId,
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Mock StripeWebhookService to return a valid event
        $stripeEvent = \Stripe\Event::constructFrom(json_decode($payload, true));
        $webhookServiceMock = Mockery::mock(StripeWebhookService::class);
        $webhookServiceMock->shouldReceive('constructEvent')
            ->once()
            ->with($payload, $signature, $this->webhookSecret)
            ->andReturn($stripeEvent);
        $this->app->instance(StripeWebhookService::class, $webhookServiceMock);

        // Mock CreditAccountService with singleton pattern
        $creditServiceMock = Mockery::mock(CreditAccountService::class);
        $creditServiceMock->shouldReceive('addCredit')
            ->once()
            ->with(
                'user',
                $user->id,
                'voucher_credit',
                500,
                'Stripe payment: '.$paymentIntentId
            );
        $this->app->singleton(CreditAccountService::class, function () use ($creditServiceMock) {
            return $creditServiceMock;
        });

        // Act
        $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        )->assertOk();
    }

    private function createWebhookPayload(string $type, array $data): string
    {
        $eventId = 'evt_test_'.time().'_'.mt_rand(1000, 9999);

        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2023-10-16',
            'created' => time(),
            'type' => $type,
            'data' => [
                'object' => $data,
            ],
        ]);
    }

    private function generateWebhookSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
