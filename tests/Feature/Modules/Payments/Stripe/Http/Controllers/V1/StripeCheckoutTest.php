<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

use App\Integrations\Payments\Stripe\Services\StripeService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('payments')]
#[Group('stripe')]
class StripeCheckoutTest extends ProtectedRouteTestCase
{
    protected MockInterface $stripeServiceMock;

    protected bool $checkAuth = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up Stripe configuration for tests
        config(['services.stripe.secret_key' => 'sk_test_fake_key_123456789']);

        // Initialize authenticated user
        $this->auth = $this->createAuthUser();

        // Create a partial mock that will use the fake config
        $this->stripeServiceMock = Mockery::mock(StripeService::class)->makePartial();
        $this->app->instance(StripeService::class, $this->stripeServiceMock);
    }

    #[Test]
    public function authenticated_user_can_create_checkout_session(): void
    {
        // Arrange
        $this->stripeServiceMock
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'id' => 'cs_test_123456',
                'url' => 'https://checkout.stripe.com/test_session',
            ]);

        $payload = [
            'amount' => 50.00,
            'currency' => 'eur',
            'credit_type' => 'voucher_credit',
            'credit_amount' => 500,
            'success_url' => 'https://app.test/success',
            'cancel_url' => 'https://app.test/cancel',
        ];

        // Act & Assert
        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertOk()
            ->assertJsonStructure([
                'checkout_url',
                'session_id',
                'payment' => [
                    'id',
                    'status',
                    'amount',
                    'currency',
                ],
            ]);

        // Verify payment was created in database
        $this->assertDatabaseHas('int_stripe_payments', [
            'user_id' => $this->auth->id,
            'stripe_checkout_id' => 'cs_test_123456',
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function validation_fails_with_invalid_data(): void
    {
        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'credit_type', 'credit_amount', 'success_url', 'cancel_url']);
    }

    #[Test]
    public function amount_must_be_numeric_and_within_range(): void
    {
        $payload = [
            'amount' => 'invalid',
            'credit_type' => 'voucher_credit',
            'credit_amount' => 500,
            'success_url' => 'https://app.test/success',
            'cancel_url' => 'https://app.test/cancel',
        ];

        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Test minimum amount
        $payload['amount'] = 0;
        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Test maximum amount
        $payload['amount'] = 100000;
        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function credit_type_must_be_valid(): void
    {
        $payload = [
            'amount' => 50.00,
            'credit_type' => 'invalid_type',
            'credit_amount' => 500,
            'success_url' => 'https://app.test/success',
            'cancel_url' => 'https://app.test/cancel',
        ];

        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['credit_type']);
    }

    #[Test]
    public function currency_defaults_to_eur_when_not_provided(): void
    {
        // Mock StripeService for this test
        $this->stripeServiceMock
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'id' => 'cs_test_123456',
                'url' => 'https://checkout.stripe.com/test_session',
            ]);

        $payload = [
            'amount' => 50.00,
            'credit_type' => 'voucher_credit',
            'credit_amount' => 500,
            'success_url' => 'https://app.test/success',
            'cancel_url' => 'https://app.test/cancel',
        ];

        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertOk();

        $this->assertEquals('eur', $response->json('payment.currency'));
    }

    #[Test]
    public function urls_must_be_valid(): void
    {
        $payload = [
            'amount' => 50.00,
            'credit_type' => 'voucher_credit',
            'credit_amount' => 500,
            'success_url' => 'not-a-url',
            'cancel_url' => 'also-not-a-url',
        ];

        $this->actingAs($this->auth)
            ->postJson('/api/v1/payments/stripe/checkout', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['success_url', 'cancel_url']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
