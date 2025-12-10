<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Payments\Stripe\Http\Controllers\V1;

use App\Enums\CreditTypes;
use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Integrations\Payments\Stripe\Services\StripeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('stripe')]
class PaymentControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private MockInterface $stripeService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripeService = Mockery::mock(StripeService::class);
        $this->app->instance(StripeService::class, $this->stripeService);
    }

    #[Test]
    public function it_creates_payment_intent_for_authenticated_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'payment-test-'.uniqid().'@example.com',
        ]);

        $paymentIntentId = 'pi_test_'.uniqid();
        $paymentIntentData = (object) [
            'id' => $paymentIntentId,
            'amount' => 10000,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_secret_'.uniqid(),
            'metadata' => ['user_id' => (string) $user->id],
        ];

        $this->stripeService
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->with(100.00, Mockery::on(function (array $metadata) use ($user): bool {
                return $metadata['user_id'] === (string) $user->id
                    && isset($metadata['order_id'])
                    && $metadata['order_id'] === '12345';
            }))
            ->andReturn($paymentIntentData);

        $requestData = [
            'amount' => 10000, // Amount in cents
            'credit_amount' => 1000,
            'credit_type' => 'cash',
            'metadata' => ['order_id' => '12345'],
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/payments/stripe/payment-intent', $requestData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'client_secret',
                    'payment_intent_id',
                    'amount',
                    'currency',
                    'status',
                    'credit_amount',
                    'credit_type',
                ],
            ])
            ->assertJson([
                'data' => [
                    'payment_intent_id' => $paymentIntentId,
                    'client_secret' => $paymentIntentData->client_secret,
                    'amount' => 10000,
                    'currency' => 'eur',
                    'credit_amount' => 1000,
                    'credit_type' => 'cash',
                ],
            ]);

        // Verify payment was created in database
        $this->assertDatabaseHas('int_stripe_payments', [
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 10000,
            'credit_amount' => 1000,
            'credit_type' => 'cash',
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'validation-test-'.uniqid().'@example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/payments/stripe/payment-intent', []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function it_validates_minimum_amount(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'min-amount-test-'.uniqid().'@example.com',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/payments/stripe/payment-intent', [
                'amount' => 0,
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    #[Test]
    public function it_uses_default_values_when_not_provided(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'default-test-'.uniqid().'@example.com',
        ]);

        $paymentIntentData = (object) [
            'id' => 'pi_test_default',
            'amount' => 5000,
            'currency' => 'eur',
            'status' => 'requires_payment_method',
            'client_secret' => 'pi_test_secret_default',
            'metadata' => ['user_id' => (string) $user->id],
        ];

        $this->stripeService
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->with(0.50, Mockery::on(function (array $metadata) use ($user): bool {
                return $metadata['user_id'] === (string) $user->id;
            }))
            ->andReturn($paymentIntentData);

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/payments/stripe/payment-intent', [
                'amount' => 50.00,
            ]);

        // Assert
        $response->assertStatus(201);

        $this->assertDatabaseHas('int_stripe_payments', [
            'user_id' => $user->id,
            'credit_amount' => 0, // Default
            'credit_type' => CreditTypes::CASH, // Default
            'currency' => 'eur', // Default
        ]);
    }

    #[Test]
    public function it_lists_user_payments(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'list-test-'.uniqid().'@example.com',
        ]);

        // Create multiple payments
        $payments = [];
        for ($i = 0; $i < 3; $i++) {
            $payments[] = StripePayment::create([
                'user_id' => $user->id,
                'stripe_payment_id' => 'pi_test_'.$i,
                'amount' => 50.00 + ($i * 10),
                'currency' => 'eur',
                'status' => $i === 0 ? 'completed' : 'pending',
                'credit_amount' => 500 + ($i * 100),
                'credit_type' => 'cash',
                'metadata' => [],
                'processed_at' => $i === 0 ? now() : null,
            ]);
        }

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/v1/payments/stripe/payments');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'stripe_payment_id',
                        'amount',
                        'currency',
                        'status',
                        'credit_amount',
                        'credit_type',
                        'created_at',
                        'processed_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_shows_specific_payment_for_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'show-test-'.uniqid().'@example.com',
        ]);

        $payment = StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => 'pi_test_show',
            'amount' => 75.00,
            'currency' => 'eur',
            'status' => 'completed',
            'credit_amount' => 750,
            'credit_type' => 'premium',
            'metadata' => ['note' => 'Test payment'],
            'processed_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/v1/payments/stripe/payments/{$payment->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $payment->id,
                    'stripe_payment_id' => 'pi_test_show',
                    'amount' => 75.00,
                    'status' => 'completed',
                    'credit_amount' => 750,
                    'credit_type' => 'premium',
                ],
            ]);
    }

    #[Test]
    public function it_prevents_viewing_other_users_payment(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser([
            'email' => 'user1-'.uniqid().'@example.com',
        ]);
        $user2 = ModelFactory::createUser([
            'email' => 'user2-'.uniqid().'@example.com',
        ]);

        $payment = StripePayment::create([
            'user_id' => $user2->id,
            'stripe_payment_id' => 'pi_test_other',
            'amount' => 100.00,
            'currency' => 'eur',
            'status' => 'completed',
            'credit_amount' => 1000,
            'credit_type' => 'cash',
            'metadata' => [],
        ]);

        // Act
        $response = $this->actingAs($user1)
            ->getJson("/api/v1/payments/stripe/payments/{$payment->id}");

        // Assert
        $response->assertStatus(404);
    }

    #[Test]
    public function it_cancels_payment_intent_successfully(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'cancel-test-'.uniqid().'@example.com',
        ]);

        $paymentIntentId = 'pi_test_cancel';
        $payment = StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_amount' => 500,
            'credit_type' => 'cash',
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
        $response = $this->actingAs($user)
            ->postJson("/api/v1/payments/stripe/payment-intent/{$paymentIntentId}/cancel");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'payment_intent_id',
                    'status',
                    'cancelled',
                    'cancelled_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_intent_id' => $paymentIntentId,
                    'status' => 'cancelled',
                    'cancelled' => true,
                ],
            ]);

        // Verify payment was updated in database
        $this->assertDatabaseHas('int_stripe_payments', [
            'id' => $payment->id,
            'status' => 'cancelled',
        ]);

        // Verify payment status was updated
        $payment->refresh();
        $this->assertEquals('cancelled', $payment->status);
    }

    #[Test]
    public function it_validates_payment_intent_belongs_to_user_on_cancel(): void
    {
        // Arrange
        $user1 = ModelFactory::createUser([
            'email' => 'cancel-user1-'.uniqid().'@example.com',
        ]);
        $user2 = ModelFactory::createUser([
            'email' => 'cancel-user2-'.uniqid().'@example.com',
        ]);

        $paymentIntentId = 'pi_test_other_user';
        StripePayment::create([
            'user_id' => $user2->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'pending',
            'credit_amount' => 500,
            'credit_type' => 'cash',
            'metadata' => [],
        ]);

        // Act
        $response = $this->actingAs($user1)
            ->postJson("/api/v1/payments/stripe/payment-intent/{$paymentIntentId}/cancel");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Payment cancellation failed',
            ]);
    }

    #[Test]
    public function it_prevents_cancelling_completed_payment(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'cancel-completed-'.uniqid().'@example.com',
        ]);

        $paymentIntentId = 'pi_test_completed';
        StripePayment::create([
            'user_id' => $user->id,
            'stripe_payment_id' => $paymentIntentId,
            'amount' => 50.00,
            'currency' => 'eur',
            'status' => 'completed',
            'credit_amount' => 500,
            'credit_type' => 'cash',
            'metadata' => [],
            'processed_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($user)
            ->postJson("/api/v1/payments/stripe/payment-intent/{$paymentIntentId}/cancel");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Payment cancellation failed',
            ]);
    }

    #[Test]
    public function it_handles_nonexistent_payment_intent_on_cancel(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'cancel-nonexistent-'.uniqid().'@example.com',
        ]);

        $nonexistentPaymentIntentId = 'pi_nonexistent';

        // Act
        $response = $this->actingAs($user)
            ->postJson("/api/v1/payments/stripe/payment-intent/{$nonexistentPaymentIntentId}/cancel");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Payment cancellation failed',
            ]);
    }
}
