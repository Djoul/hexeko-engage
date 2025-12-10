<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

use App\Integrations\Payments\Stripe\Events\StripePaymentFailed;
use App\Integrations\Payments\Stripe\Events\StripePaymentSucceeded;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('payments')]
#[Group('stripe')]
class StripeWebhookFlowTest extends TestCase
{
    use DatabaseTransactions;

    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test webhook secret
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);

        // Fake events to capture them
        Event::fake([
            StripePaymentSucceeded::class,
            StripePaymentFailed::class,
        ]);
    }

    #[Test]
    public function it_processes_payment_intent_succeeded_webhook(): void
    {
        // Arrange
        $user = User::factory()->create();
        $payment = StripePayment::create([
            'stripe_payment_id' => 'pi_test_123',
            'user_id' => $user->id,
            'amount' => 1000,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_type' => 'standard',
            'credit_amount' => 100,
        ]);

        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'id' => 'pi_test_123',
            'amount' => 1000,
            'currency' => 'eur',
            'status' => 'succeeded',
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Act
        $response = $this->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $signature,
        ]);

        // Assert
        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertNotNull($payment->processed_at);

        Event::assertDispatched(StripePaymentSucceeded::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id;
        });
    }

    #[Test]
    public function it_processes_checkout_session_completed_webhook(): void
    {
        // Arrange
        $user = User::factory()->create();
        $payment = StripePayment::create([
            'stripe_checkout_id' => 'cs_test_123',
            'user_id' => $user->id,
            'amount' => 2000,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_type' => 'premium',
            'credit_amount' => 200,
        ]);

        $payload = $this->createWebhookPayload('checkout.session.completed', [
            'id' => 'cs_test_123',
            'payment_intent' => 'pi_test_456',
            'amount_total' => 2000,
            'currency' => 'eur',
            'payment_status' => 'paid',
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Act
        $response = $this->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $signature,
        ]);

        // Assert
        $response->assertOk();

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals('pi_test_456', $payment->stripe_payment_id);

        Event::assertDispatched(StripePaymentSucceeded::class);
    }

    #[Test]
    public function it_processes_payment_intent_failed_webhook(): void
    {
        // Arrange
        $user = User::factory()->create();
        $payment = StripePayment::create([
            'stripe_payment_id' => 'pi_test_failed',
            'user_id' => $user->id,
            'amount' => 1500,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_type' => 'standard',
            'credit_amount' => 150,
        ]);

        $payload = $this->createWebhookPayload('payment_intent.payment_failed', [
            'id' => 'pi_test_failed',
            'amount' => 1500,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
            'last_payment_error' => [
                'code' => 'card_declined',
                'message' => 'Your card was declined.',
            ],
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Act
        $response = $this->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $signature,
        ]);

        // Assert
        $response->assertOk();

        $payment->refresh();
        $this->assertEquals('failed', $payment->status);

        Event::assertDispatched(StripePaymentFailed::class, function ($event) use ($payment): bool {
            return $event->payment->id === $payment->id;
        });
    }

    #[Test]
    public function it_rejects_webhook_with_invalid_signature(): void
    {
        // Arrange
        $payload = $this->createWebhookPayload('payment_intent.succeeded', [
            'id' => 'pi_test_invalid',
        ]);

        // Act
        $response = $this->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        // Assert
        $response->assertStatus(400);
        Event::assertNothingDispatched();
    }

    #[Test]
    public function it_handles_unrecognized_event_types(): void
    {
        // Arrange
        $payload = $this->createWebhookPayload('customer.created', [
            'id' => 'cus_test_123',
        ]);

        $signature = $this->generateWebhookSignature($payload);

        // Act
        $response = $this->postJson('/api/v1/payments/stripe/webhook', json_decode($payload, true), [
            'Stripe-Signature' => $signature,
        ]);

        // Assert
        $response->assertOk();
        Event::assertNothingDispatched();
    }

    /**
     * Create a webhook payload.
     */
    private function createWebhookPayload(string $type, array $data): string
    {
        return json_encode([
            'id' => 'evt_'.uniqid(),
            'type' => $type,
            'created' => time(),
            'data' => [
                'object' => $data,
            ],
        ]);
    }

    /**
     * Generate a webhook signature for testing.
     * In tests, we use a simplified signature that will be accepted.
     */
    private function generateWebhookSignature(string $payload): string
    {
        $timestamp = time();
        $signedPayload = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signedPayload, $this->webhookSecret);

        return "t={$timestamp},v1={$signature}";
    }
}
