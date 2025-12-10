<?php

namespace Tests\Unit\Modules\Vouchers\Amilon\Services;

use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\OrderItem;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonOrderServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AmilonOrderService $orderService;

    private MockObject $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = $this->createMock(AmilonAuthService::class);
        $this->authService->method('getAccessToken')->willReturn('fake-token');
        $this->authService->method('refreshToken')->willReturn('fake-token');

        $this->orderService = new AmilonOrderService($this->authService);

        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    #[Test]
    public function it_optimizes_product_queries_when_getting_order_info(): void
    {
        // Arrange
        $externalOrderId = 'test-order-123';

        $merchantId = Uuid::uuid4()->toString();

        // Create merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant',
            'merchant_id' => $merchantId,
            'country' => 'FR',
        ]);
        // Create test products
        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = Product::create([
                'product_code' => "PROD{$i}",
                'name' => "Product {$i}",
                'price' => 100 * $i,
                'merchant_id' => $merchantId,
            ]);
        }
        // Create test order
        $order = Order::create([
            'external_order_id' => $externalOrderId,
            'status' => 'pending',
            'order_status' => 'PENDING',
            'total_amount' => 1500,
            'amount' => 1500,
            'merchant_id' => $merchant->id,
        ]);

        // Create order items
        foreach ($products as $product) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $product->price,
            ]);
        }

        // Mock API response
        $apiResponse = [
            'orderStatus' => 'confirmed',
            'orderRows' => array_map(function ($product): array {
                return [
                    'productId' => $product->product_code,
                    'vouchers' => [
                        ['code' => 'VOUCHER-'.$product->product_code, 'status' => 'ACTIVE'],
                    ],
                ];
            }, $products),
        ];

        Http::fake([
            '*' => Http::response($apiResponse, 200),
        ]);

        // Enable query logging to count queries
        DB::enableQueryLog();

        // Act
        $result = $this->orderService->getOrderInfo($externalOrderId);

        // Get executed queries
        $queries = DB::getQueryLog();

        // Filter queries on int_vouchers_amilon_products table
        $productQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'int_vouchers_amilon_products');
        });

        // Assert
        // Should have only 1 query for products (using whereIn), not 5 individual queries
        $this->assertLessThanOrEqual(1, count($productQueries),
            'Expected 1 optimized query with whereIn, but got '.count($productQueries).' queries'
        );

        // Verify the result is correct
        $this->assertEquals('confirmed', $result['order_status']);
        $this->assertCount(5, $result['items']);

        // Verify vouchers were saved
        $updatedOrder = Order::find($order->id);
        foreach ($updatedOrder->items as $item) {
            $this->assertNotNull($item->vouchers);
            $this->assertIsArray($item->vouchers);
        }
    }

    #[Test]
    public function it_handles_empty_order_rows_gracefully(): void
    {
        // Arrange
        $externalOrderId = 'test-order-456';
        $merchantId = Uuid::uuid4()->toString();
        // Create merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant',
            'merchant_id' => $merchantId,
            'country' => 'FR',
        ]);

        Order::create([
            'external_order_id' => $externalOrderId,
            'status' => 'pending',
            'order_status' => 'PENDING',
            'total_amount' => 0,
            'amount' => 0,
            'merchant_id' => $merchant->id,
        ]);

        $apiResponse = [
            'orderStatus' => 'COMPLETED',
            'orderRows' => [],
        ];

        Http::fake([
            '*' => Http::response($apiResponse, 200),
        ]);

        // Act
        $result = $this->orderService->getOrderInfo($externalOrderId);

        // Assert
        $this->assertEquals('COMPLETED', $result['order_status']);
        $this->assertArrayHasKey('items', $result);
    }

    #[Test]
    public function it_handles_missing_products_gracefully(): void
    {
        // Arrange
        $externalOrderId = 'test-order-789';
        $merchantId = Uuid::uuid4()->toString();
        // Create merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant',
            'merchant_id' => $merchantId,
            'country' => 'FR',
        ]);

        Order::create([
            'external_order_id' => $externalOrderId,
            'status' => 'pending',
            'order_status' => 'PENDING',
            'total_amount' => 500,
            'amount' => 500,
            'merchant_id' => $merchant->id,
        ]);

        $apiResponse = [
            'orderStatus' => 'confirmed',
            'orderRows' => [
                [
                    'productId' => 'NON_EXISTENT_PROD',
                    'vouchers' => [
                        ['code' => 'VOUCHER-123', 'status' => 'ACTIVE'],
                    ],
                ],
            ],
        ];

        Http::fake([
            '*' => Http::response($apiResponse, 200),
        ]);

        // Act & Assert - Should not throw exception
        $result = $this->orderService->getOrderInfo($externalOrderId);
        $this->assertEquals('confirmed', $result['order_status']);
    }
}
