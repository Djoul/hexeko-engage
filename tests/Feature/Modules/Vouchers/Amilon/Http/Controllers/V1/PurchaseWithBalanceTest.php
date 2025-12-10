<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Payments\Stripe\Services\StripeService;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Models\User;
use App\Services\CreditAccountService;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('vouchers')]
class PurchaseWithBalanceTest extends ProtectedRouteTestCase
{
    private MockInterface $stripeService;

    private MockInterface $amilonService;

    private Merchant $merchant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser();
        Event::fake();

        // Set up Stripe config for tests
        config(['services.stripe.secret_key' => 'sk_test_123456789']);

        // Mock Stripe service
        $this->stripeService = Mockery::mock(StripeService::class);
        $this->app->instance(StripeService::class, $this->stripeService);

        // Mock Amilon service
        $this->amilonService = Mockery::mock(AmilonOrderService::class);
        $this->app->instance(AmilonOrderService::class, $this->amilonService);

        $this->merchant = Merchant::factory()->create();

    }

    #[Test]
    public function it_returns_deprecated_error_for_payment_options_endpoint(): void
    {
        $user = $this->auth;

        $response = $this->actingAs($user)
            ->getJson('/api/v1/vouchers/amilon/payment-options');

        // The endpoint now throws a DeprecatedFeatureException
        $response->assertStatus(410) // 410 Gone
            ->assertJsonStructure([
                'error',
                'message',
                'deprecated_feature',
                'replacement',
                'deprecated_since',
            ])
            ->assertJson([
                'error' => 'deprecated_feature',
                'deprecated_feature' => 'GET /api/v1/vouchers/amilon/payment-options endpoint',
                'replacement' => 'POST /api/v1/vouchers/amilon/purchase endpoint with appropriate payment method',
                'deprecated_since' => '2025-08-06',
            ]);
    }

    #[Test]
    public function it_completes_purchase_with_balance_only(): void
    {
        $user = $this->auth;
        // Create product with specific price and net_price
        $product = resolve(ProductFactory::class)->forMerchant($this->merchant)->create([
            'price' => 5000,
            'net_price' => 4500, // 10% less than price
        ]);
        // Add sufficient balance for net_price
        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        // Mock Amilon service to return success
        $this->amilonService->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'merchant_id' => $product->merchant_id,
                'amount' => $product->price,
                'external_order_id' => 'ENGAGE-2025-TEST123',
                'order_id' => 'amilon-order-123',
                'status' => 'completed',
                'price_paid' => $product->price,
                'voucher_url' => 'https://example.com/voucher',
                'created_at' => now()->toDateTimeString(),
                'payment_id' => null,
                'items' => [],
                'order_date' => now()->toDateTimeString(),
                'gross_amount' => $product->price,
                'net_amount' => $product->net_price,
                'total_requested_codes' => 1,
                'order_status' => 'completed',
            ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/vouchers/amilon/purchase', [
                'product_id' => $product->id,
                'payment_method' => 'balance',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'user_id' => $user->id,
            'payment_method' => 'balance',
            'total_amount' => '5000',
        ]);

        // Verify balance was deducted by net_price amount
        $this->assertDatabaseHas('credit_balances', [
            'owner_id' => $user->id,
            'type' => 'cash',
            'balance' => 5500, // 10000 - 4500 (net_price)
        ]);
    }

    #[Test]
    public function it_fails_when_balance_is_insufficient(): void
    {
        $user = $this->auth;
        $product = resolve(ProductFactory::class)->forMerchant($this->merchant)->create([
            'price' => 15000,
            'net_price' => 13500, // 10% less than price
        ]);
        // Add balance less than net_price
        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/vouchers/amilon/purchase', [
                'product_id' => $product->id,
                'payment_method' => 'balance',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Insufficient balance for this purchase',
            ]);

        // Verify balance was not deducted
        $this->assertDatabaseHas('credit_balances', [
            'owner_id' => $user->id,
            'type' => 'cash',
            'balance' => 10000,
        ]);
    }

    #[Test]
    public function it_handles_exact_balance_amount(): void
    {
        $user = $this->auth;
        $product = resolve(ProductFactory::class)->forMerchant($this->merchant)->create([
            'price' => 7550,
            'net_price' => 6795, // 10% less than price
        ]);
        CreditAccountService::addCredit(User::class, $user->id, 'cash', 6795); // Add exact net_price amount

        // Mock Amilon service to return success
        $this->amilonService->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'merchant_id' => $product->merchant_id,
                'amount' => $product->price,
                'external_order_id' => 'ENGAGE-2025-TEST456',
                'order_id' => 'amilon-order-456',
                'status' => 'completed',
                'price_paid' => $product->price,
                'voucher_url' => 'https://example.com/voucher456',
                'created_at' => now()->toDateTimeString(),
                'payment_id' => null,
                'items' => [],
                'order_date' => now()->toDateTimeString(),
                'gross_amount' => $product->price,
                'net_amount' => $product->net_price,
                'total_requested_codes' => 1,
                'order_status' => 'completed',
            ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/vouchers/amilon/purchase', [
                'product_id' => $product->id,
                'payment_method' => 'balance',
            ]);

        $response->assertCreated();

        // Verify balance is now zero
        $this->assertDatabaseHas('credit_balances', [
            'owner_id' => $user->id,
            'type' => 'cash',
            'balance' => 0, // Exact net_price was deducted
        ]);

        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'user_id' => $user->id,
            'payment_method' => 'balance',
            'total_amount' => '7550',
        ]);
    }

    #[Test]
    public function it_validates_product_exists(): void
    {
        $user = $this->auth;

        $response = $this->actingAs($user)
            ->postJson('/api/v1/vouchers/amilon/purchase', [
                'product_id' => '550e8400-e29b-41d4-a716-446655440000', // Non-existent UUID
                'payment_method' => 'balance',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    #[Test]
    public function it_validates_payment_method(): void
    {
        $user = $this->auth;
        $product = resolve(ProductFactory::class)->forMerchant($this->merchant)->create();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/vouchers/amilon/purchase', [
                'product_id' => $product->id,
                'payment_method' => 'invalid_method',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }
}
