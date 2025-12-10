<?php

namespace Tests\Unit\Modules\Vouchers\Amilon\Http\Resources;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderItemFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Http\Resources\OrderResource;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class OrderResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_exposes_all_necessary_order_fields(): void
    {
        // Given
        $user = User::factory()->create();
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'product_id' => $product->id,
            'voucher_code' => 'VOUCHER123',
            'metadata' => ['source' => 'web', 'campaign' => 'summer'],
            'payment_method' => 'stripe',
            // 'payment_completed_at' => now(), // Column removed
            'total_amount' => 150.00,
        ]);

        // When
        $resource = new OrderResource($order);
        $response = $resource->toArray(app(Request::class));

        // Then
        $this->assertArrayHasKey('voucher_code', $response);
        $this->assertEquals('VOUCHER123', $response['voucher_code']);

        $this->assertArrayHasKey('metadata', $response);
        $this->assertEquals(['source' => 'web', 'campaign' => 'summer'], $response['metadata']);

        $this->assertArrayHasKey('payment_method', $response);
        $this->assertEquals('stripe', $response['payment_method']);

        // $this->assertArrayHasKey('payment_completed_at', $response); // Column removed
        // $this->assertNotNull($response['payment_completed_at']);

        $this->assertArrayHasKey('total_amount', $response);
        $this->assertEquals(150.00, $response['total_amount']);
    }

    #[Test]
    public function it_includes_voucher_activation_links(): void
    {
        // Given
        $user = User::factory()->create();
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        $order = resolve(OrderFactory::class)->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
        ]);

        // Create order items with vouchers
        resolve(OrderItemFactory::class)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'vouchers' => [
                [
                    'code' => 'VOUCHER001',
                    'url' => 'https://my-gate.amilon.eu/?pin=ABC123',
                    'voucherLink' => 'https://my-gate.amilon.eu/?pin=ABC123',
                    'validityStartDate' => '2025-01-01T00:00:00Z',
                    'validityEndDate' => '2025-12-31T23:59:59Z',
                ],
                [
                    'code' => 'VOUCHER002',
                    'url' => 'https://my-gate.amilon.eu/?pin=DEF456',
                    'voucherLink' => 'https://my-gate.amilon.eu/?pin=DEF456',
                    'validityStartDate' => '2025-01-01T00:00:00Z',
                    'validityEndDate' => '2025-12-31T23:59:59Z',
                ],
            ],
        ]);

        resolve(OrderItemFactory::class)->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'vouchers' => [
                [
                    'code' => 'VOUCHER003',
                    'url' => 'https://my-gate.amilon.eu/?pin=GHI789',
                    'voucherLink' => 'https://my-gate.amilon.eu/?pin=GHI789',
                    'validityStartDate' => '2025-01-01T00:00:00Z',
                    'validityEndDate' => '2025-12-31T23:59:59Z',
                ],
            ],
        ]);

        // When
        $order->load('items');
        $resource = new OrderResource($order);
        $response = $resource->toArray(app(Request::class));

        // Then
        $this->assertNotEmpty($response['items']);
        $this->assertCount(2, $response['items']);

        // Check first item vouchers
        $this->assertNotNull($response['items'][0]['vouchers']);
        $this->assertCount(2, $response['items'][0]['vouchers']);
        $this->assertArrayHasKey('url', $response['items'][0]['vouchers'][0]);
        $this->assertArrayHasKey('code', $response['items'][0]['vouchers'][0]);
        $this->assertEquals('VOUCHER001', $response['items'][0]['vouchers'][0]['code']);
        $this->assertEquals('https://my-gate.amilon.eu/?pin=ABC123', $response['items'][0]['vouchers'][0]['url']);

        // Check second item vouchers
        $this->assertNotNull($response['items'][1]['vouchers']);
        $this->assertCount(1, $response['items'][1]['vouchers']);
        $this->assertArrayHasKey('url', $response['items'][1]['vouchers'][0]);
        $this->assertArrayHasKey('code', $response['items'][1]['vouchers'][0]);
        $this->assertEquals('VOUCHER003', $response['items'][1]['vouchers'][0]['code']);
    }

    #[Test]
    public function it_includes_merchant_relation_when_loaded(): void
    {
        // Given
        $merchant = resolve(MerchantFactory::class)->create(['name' => 'Test Merchant']);
        $order = resolve(OrderFactory::class)->create([
            'merchant_id' => $merchant->merchant_id,
        ]);

        // When - without loading merchant
        $resource = new OrderResource($order);
        $response = $resource->toArray(app(Request::class));

        // Then - merchant should not be loaded
        $this->assertArrayHasKey('merchant', $response);
        $this->assertNull($response['merchant']);

        // When - with loaded merchant
        $order->load('merchant');
        $resource = new OrderResource($order);
        $response = $resource->toArray(app(Request::class));

        // Then - merchant should be present
        $this->assertArrayHasKey('merchant', $response);
        $this->assertEquals('Test Merchant', $response['merchant']['name']);
    }

    #[Test]
    public function it_handles_null_values_gracefully(): void
    {
        // Given
        $order = resolve(OrderFactory::class)->create([
            'voucher_code' => null,
            'metadata' => null,
            'payment_method' => null,
            // 'payment_completed_at' => null, // Column removed
            'total_amount' => null,
        ]);

        // When
        $resource = new OrderResource($order);
        $response = $resource->toArray(app(Request::class));

        // Then - all fields should be present even if null
        $this->assertArrayHasKey('voucher_code', $response);
        $this->assertNull($response['voucher_code']);

        $this->assertArrayHasKey('metadata', $response);
        $this->assertNull($response['metadata']);

        $this->assertArrayHasKey('payment_method', $response);
        $this->assertNull($response['payment_method']);

        // $this->assertArrayHasKey('payment_completed_at', $response); // Column removed
        // $this->assertNull($response['payment_completed_at']);

        $this->assertArrayHasKey('total_amount', $response);
        $this->assertNull($response['total_amount']);
    }
}
