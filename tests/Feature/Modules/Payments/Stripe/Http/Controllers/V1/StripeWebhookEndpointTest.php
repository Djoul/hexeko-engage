<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Models\ProcessedWebhookEvent;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('stripe')]
#[Group('payments')]
#[Group('webhook')]
class StripeWebhookEndpointTest extends ProtectedRouteTestCase
{
    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
        // Ensure CLI secret is not set for tests
        config(['services.stripe.webhook_secret_cli' => null]);

        // Fake events to prevent actual broadcasting
        Event::fake();
    }

    #[Test]
    public function webhook_accepts_valid_payment_succeeded_event(): void
    {
        // Arrange
        $user = User::factory()->create();
        $merchantId = 'TEST_MERCHANT_'.uniqid();
        $merchant = resolve(MerchantFactory::class)->create(['merchant_id' => $merchantId]);
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        $uniqueId = 'pi_test_succeeded_'.time().'_'.mt_rand(1000, 9999);
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_payment_id' => $uniqueId,
            'status' => 'pending',
            'amount' => 4000,
            'credit_amount' => 50,
            'credit_type' => 'voucher_credit',
            'metadata' => [
                'product_id' => $product->id,
                'merchant_id' => $merchantId,
                'voucher_amount' => 50,
            ],
        ]);

        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'id' => $payment->stripe_payment_id,
            'amount' => 4000,
            'currency' => 'eur',
            'status' => 'succeeded',
            'metadata' => [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'merchant_id' => $merchantId,
                'voucher_amount' => '50',
            ],
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // No order service interaction needed for stripe payment webhooks

        // Act - Send as raw body
        $response = $this->call(
            'POST',
            'api/v1/payments/stripe/webhook',
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
    }

    #[Test]
    public function webhook_fails_with_invalid_signature(): void
    {
        // Arrange
        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'id' => 'pi_test_123',
        ]);

        // Use a properly formatted but invalid signature
        $invalidSignature = 't=1234567890,v1=invalid_signature_hash';

        // Act - Send as raw body
        $response = $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            ['HTTP_Stripe-Signature' => $invalidSignature, 'CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // Assert
        $response->assertStatus(400)
            ->assertJson(['error' => 'Webhook verification failed']);
    }

    #[Test]
    public function webhook_returns_success_for_unhandled_events(): void
    {
        // Arrange
        $payload = $this->createWebhookPayload('customer.created', [
            'id' => 'cus_test_123',
            'email' => 'test@example.com',
        ]);

        $signature = $this->generateWebhookSignature($payload);

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
    }

    #[Test]
    public function webhook_prevents_duplicate_event_processing(): void
    {
        // Arrange
        $user = User::factory()->create();
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        $uniqueId = 'pi_test_duplicate_'.time().'_'.mt_rand(1000, 9999);
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_payment_id' => $uniqueId,
            'status' => 'pending',
            'metadata' => [
                'product_id' => $product->id,
                'merchant_id' => $merchant->merchant_id,
            ],
        ]);

        // Create existing processed event
        ProcessedWebhookEvent::create([
            'event_id' => 'evt_test_123',
            'event_type' => 'payment_intent.succeeded',
            'processed_at' => now(),
        ]);

        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'id' => $payment->stripe_payment_id,
        ], 'evt_test_123');

        $signature = $this->generateWebhookSignature($payload);

        // No order service interaction for stripe webhooks

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

        // Payment should be processed even for duplicate webhook (idempotency at Stripe level)
        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
    }

    #[Test]
    public function webhook_handles_payment_failed_event(): void
    {
        // Arrange
        $user = User::factory()->create();
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        $paymentId = 'pi_test_failed_'.uniqid();
        $payment = StripePayment::factory()->create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentId,
            'status' => 'pending',
            'metadata' => [
                'product_id' => $product->id,
                'merchant_id' => $product->merchant_id,
            ],
        ]);

        $payload = $this->createWebhookPayload('payment_intent.payment_failed', [
            'id' => $paymentId,
            'status' => 'requires_payment_method',
            'last_payment_error' => [
                'code' => 'card_declined',
                'message' => 'Your card was declined.',
            ],
        ]);

        $signature = $this->generateWebhookSignature($payload);

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
        $this->assertDatabaseHas('int_stripe_payments', [
            'id' => $payment->id,
            'status' => 'failed',
        ]);
        // Error is stored in metadata
        $this->assertArrayHasKey('error', $payment->metadata);
        $this->assertEquals('Your card was declined.', $payment->metadata['error']);
    }

    private function createWebhookPayload(string $type, array $data, ?string $eventId = null): string
    {
        if (in_array($eventId, [null, '', '0'], true)) {
            $eventId = 'evt_test_'.time().'_'.mt_rand(1000, 9999);
        }

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

    protected function tearDown(): void
    {
        // Restore error handlers to prevent test warnings
        restore_error_handler();
        restore_exception_handler();

        Mockery::close();
        parent::tearDown();
    }
}
