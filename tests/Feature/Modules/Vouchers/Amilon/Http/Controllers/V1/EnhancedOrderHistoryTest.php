<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderItemFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class EnhancedOrderHistoryTest extends ProtectedRouteTestCase
{
    const ORDERS_HISTORY_URL = '/api/v1/vouchers/amilon/orders';

    #[Test]
    public function history_endpoint_always_includes_items_and_merchant(): void
    {
        // Given
        $user = User::factory()->create();
        /** @var Merchant $merchant */
        $merchant = resolve(MerchantFactory::class)->create(['name' => 'Fnac']);
        /** @var Product $product */
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'name' => 'Gift Card 50€',
        ]);

        /** @var Order $order */
        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'amount' => 50.00,
            'voucher_code' => 'ORDER-VOUCHER-123',
            'metadata' => ['source' => 'mobile'],
            'payment_method' => 'stripe',
            // 'payment_completed_at' => now(), // Column removed
            'total_amount' => 50.00,
        ]);

        resolve(OrderItemFactory::class)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'vouchers' => [
                [
                    'code' => 'ITEM-VOUCHER-001',
                    'url' => 'https://my-gate.amilon.eu/?pin=ABC123',
                ],
            ],
        ]);

        // When
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_HISTORY_URL);

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'amount',
                    'voucher_code',
                    'metadata',
                    'payment_method',
                    // 'payment_completed_at', // Column removed
                    'total_amount',
                    'merchant' => ['id', 'name'],
                    'items' => [
                        '*' => [
                            'id',
                            'vouchers' => [
                                '*' => ['code', 'url'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Verify specific values
        /** @var array<string, mixed> $orderData */
        $orderData = $response->json('data.0');
        $this->assertEquals('Fnac', $orderData['merchant']['name']);
        $this->assertEquals('ORDER-VOUCHER-123', $orderData['voucher_code']);
        $this->assertEquals(['source' => 'mobile'], $orderData['metadata']);
        $this->assertEquals('stripe', $orderData['payment_method']);
        $this->assertEquals(50.00, $orderData['total_amount']);

        // Verify items and vouchers
        $this->assertCount(1, $orderData['items']);
        $this->assertEquals('ITEM-VOUCHER-001', $orderData['items'][0]['vouchers'][0]['code']);
        $this->assertEquals('https://my-gate.amilon.eu/?pin=ABC123', $orderData['items'][0]['vouchers'][0]['url']);
    }

    #[Test]
    public function history_returns_orders_sorted_by_most_recent(): void
    {
        // Given
        $user = User::factory()->create();
        /** @var Merchant $merchant */
        $merchant = resolve(MerchantFactory::class)->create();

        resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'created_at' => now()->subDays(5),
            'external_order_id' => 'OLD-ORDER',
        ]);

        resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'created_at' => now()->subDays(2),
            'external_order_id' => 'MIDDLE-ORDER',
        ]);

        resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'created_at' => now(),
            'external_order_id' => 'NEW-ORDER',
        ]);

        // When
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_HISTORY_URL);

        // Then
        $response->assertOk();
        /** @var array<int, array<string, mixed>> $data */
        $data = $response->json('data');
        $this->assertCount(3, $data);
        $this->assertEquals('NEW-ORDER', $data[0]['external_order_id']);
        $this->assertEquals('MIDDLE-ORDER', $data[1]['external_order_id']);
        $this->assertEquals('OLD-ORDER', $data[2]['external_order_id']);
    }

    #[Test]
    public function history_endpoint_includes_pagination(): void
    {
        // Given
        $user = User::factory()->create();
        /** @var Merchant $merchant */
        $merchant = resolve(MerchantFactory::class)->create();

        // Create 25 orders
        for ($i = 0; $i < 25; $i++) {
            resolve(OrderFactory::class)->create([
                'user_id' => $user->id,
                'merchant_id' => $merchant->merchant_id,
                'created_at' => now()->subDays($i),
            ]);
        }

        // When - First page
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_HISTORY_URL);

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'links',
                'path',
                'per_page',
                'to',
                'total',
            ],
        ]);

        $this->assertCount(20, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
        $this->assertEquals(25, $response->json('meta.total'));
        $this->assertEquals(20, $response->json('meta.per_page'));

        // When - Second page
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_HISTORY_URL.'?page=2');

        // Then
        $response->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(2, $response->json('meta.current_page'));
    }

    #[Test]
    public function history_endpoint_loads_product_relation(): void
    {
        // Given
        $user = User::factory()->create();
        /** @var Merchant $merchant */
        $merchant = resolve(MerchantFactory::class)->create();
        /** @var Product $product */
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'name' => 'Premium Gift Card',
            'product_code' => 'PGC-100',
        ]);

        resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'product_id' => $product->id,
        ]);

        // When
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_HISTORY_URL);

        // Then
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'product' => ['id', 'name', 'product_code'],
                ],
            ],
        ]);

        /** @var array<string, mixed> $orderData */
        $orderData = $response->json('data.0');
        $this->assertEquals('Premium Gift Card', $orderData['product']['name']);
        $this->assertEquals('PGC-100', $orderData['product']['product_code']);
    }

    #[Test]
    public function history_endpoint_handles_empty_vouchers_gracefully(): void
    {
        // Given
        $user = User::factory()->create();
        /** @var Merchant $merchant */
        $merchant = resolve(MerchantFactory::class)->create();
        /** @var Product $product */
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
        ]);

        // Create order item without vouchers
        resolve(OrderItemFactory::class)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'vouchers' => null,
        ]);

        // When
        $response = $this->actingAs($user)
            ->getJson(self::ORDERS_HISTORY_URL);

        // Then
        $response->assertOk();
        /** @var array<string, mixed> $orderData */
        $orderData = $response->json('data.0');
        $this->assertArrayHasKey('items', $orderData);
        $this->assertNull($orderData['items'][0]['vouchers']);
    }
}
