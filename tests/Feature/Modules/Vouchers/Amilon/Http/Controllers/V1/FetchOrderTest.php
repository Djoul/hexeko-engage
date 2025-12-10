<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderItemFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]

class FetchOrderTest extends ProtectedRouteTestCase
{
    private const ORDERS_INDEX_URL = '/api/v1/vouchers/amilon/orders';

    protected function setUp(): void
    {
        parent::setUp();

        // Run Amilon migrations manually
        $this->artisan('migrate', [
            '--path' => 'app/Integrations/Vouchers/Amilon/Database/migrations',
            '--realpath' => false,
        ]);
    }

    #[Test]
    public function test_orders_endpoint_returns_valid_json_structure(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create a merchant
        $merchant = resolve(MerchantFactory::class)->create([
            'name' => 'Fnac',
        ]);

        // Create a product
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'name' => 'Fnac Gift Card 50€',
            'price' => 50.00,
        ]);

        // Create an order for the user
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'amount' => 50.00,
            'status' => 'completed',
        ]);

        // Create order items
        resolve(OrderItemFactory::class)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50.00,
        ]);

        // Make request to the orders endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_INDEX_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'external_order_id',
                        'order_id',
                        'status',
                        'price_paid',
                        'voucher_url',
                        'payment_id',
                        'created_at',
                        'updated_at',
                        'merchant_id',
                        'user_id',
                        'items',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_orders_endpoint_returns_unauthorized_without_user(): void
    {
        // Enable authentication checking for this test
        $this->checkAuth = true;
        $this->refreshApplication();

        // Make request to the orders endpoint without authentication
        $response = $this->getJson(self::ORDERS_INDEX_URL);

        // Assert response
        $response->assertStatus(401);
    }

    #[Test]
    public function test_orders_endpoint_returns_empty_array_when_no_orders(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Make request to the orders endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_INDEX_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function test_orders_endpoint_returns_multiple_orders(): void
    {
        // Create a user
        $user = User::factory()->create();

        // Create a merchant
        $merchant = resolve(MerchantFactory::class)->create([
            'name' => 'Fnac',
        ]);

        // Create products
        $product1 = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'name' => 'Fnac Gift Card 50€',
            'price' => 50.00,
        ]);

        $product2 = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'name' => 'Fnac Gift Card 100€',
            'price' => 100.00,
        ]);

        // Create orders for the user
        $order1 = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'amount' => 50.00,
            'status' => 'completed',
            'created_at' => now()->subDays(2),
        ]);

        $order2 = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'amount' => 100.00,
            'status' => 'completed',
            'created_at' => now()->subDay(),
        ]);

        // Create order items
        resolve(OrderItemFactory::class)->create([
            'order_id' => $order1->id,
            'product_id' => $product1->id,
            'quantity' => 1,
            'price' => 50.00,
        ]);

        resolve(OrderItemFactory::class)->create([
            'order_id' => $order2->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);

        // Make request to the orders endpoint
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_INDEX_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'amount',
                        'external_order_id',
                        'order_id',
                        'status',
                        'price_paid',
                        'voucher_url',
                        'payment_id',
                        'created_at',
                        'updated_at',
                        'merchant_id',
                        'user_id',
                        'items',
                    ],
                ],
            ]);

        // Verify that the orders are returned in descending order by created_at
        $responseData = $response->json('data');
        $this->assertEquals($order2->external_order_id, $responseData[0]['external_order_id']);
        $this->assertEquals($order1->external_order_id, $responseData[1]['external_order_id']);
    }

    #[Test]
    public function test_orders_endpoint_only_returns_user_orders(): void
    {
        // Create users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create a merchant
        $merchant = resolve(MerchantFactory::class)->create([
            'name' => 'Fnac',
        ]);

        // Create a product
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'name' => 'Fnac Gift Card 50€',
            'price' => 50.00,
        ]);

        // Create an order for user1
        $order1 = resolve(OrderFactory::class)->create([
            'user_id' => $user1->id,
            'merchant_id' => $merchant->merchant_id,
            'amount' => 50.00,
            'status' => 'completed',
        ]);

        // Create an order for user2
        $order2 = resolve(OrderFactory::class)->create([
            'user_id' => $user2->id,
            'merchant_id' => $merchant->merchant_id,
            'amount' => 100.00,
            'status' => 'completed',
        ]);

        // Create order items
        resolve(OrderItemFactory::class)->create([
            'order_id' => $order1->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50.00,
        ]);

        resolve(OrderItemFactory::class)->create([
            'order_id' => $order2->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Make request to the orders endpoint as user1
        $response = $this->actingAs($user1)
            ->getJson(self::ORDERS_INDEX_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_order_id', $order1->external_order_id);

        // Make request to the orders endpoint as user2
        $response = $this->actingAs($user2)
            ->getJson(self::ORDERS_INDEX_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.external_order_id', $order2->external_order_id);
    }
}
