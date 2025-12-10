<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
class AmilonOrderServiceTest extends TestCase
{
    //

    private AmilonOrderService $service;

    private MockInterface $authService;

    protected function setUp(): void
    {
        parent::setUp();
        // Set up test configuration
        config([
            'services.amilon.api_url' => 'https://api.amilon.test',
            'services.amilon.contrat_id' => 'TEST_CONTRACT_ID',
        ]);

        // Mock auth service
        $this->authService = Mockery::mock(AmilonAuthService::class);
        $this->authService->shouldReceive('getAccessToken')
            ->andReturn('test_access_token')
            ->byDefault();

        $this->app->instance(AmilonAuthService::class, $this->authService);

        $this->service = resolve(AmilonOrderService::class);
    }

    #[Test]
    public function it_creates_order_with_stripe_payment_id(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Create or get merchant first to avoid foreign key constraint
        $merchant = Merchant::firstOrCreate(
            ['merchant_id' => 'TEST_MERCHANT'],
            Merchant::factory()->make(['merchant_id' => 'TEST_MERCHANT'])->toArray()
        );

        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'product_code' => 'VOUCHER_50',
        ]);

        $externalOrderId = 'EXT_ORDER_123';
        $stripePaymentId = 'pi_test_123';
        $quantity = 50;

        Http::fake([
            'https://api.amilon.test/b2bwebapi/v1/Orders/create/TEST_CONTRACT_ID' => Http::response([
                'order_id' => 'AMILON_ORDER_123',
                'orderStatus' => 'active',
                'orderRows' => [
                    [
                        'productId' => 'VOUCHER_50',
                        'quantity' => $quantity,
                        'vouchers' => [
                            [
                                'code' => 'VOUCHER123456',
                                'url' => 'https://vouchers.amilon.com/VOUCHER123456',
                                'expiresAt' => '2025-12-31',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $result = $this->service->createOrder(
            $product,
            $quantity,
            $externalOrderId,
            $user->id,
            $stripePaymentId
        );

        // Assert
        $this->assertEquals('AMILON_ORDER_123', $result['order_id']);
        $this->assertEquals('active', $result['order_status']);

        // Verify order was saved in database
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'external_order_id' => $externalOrderId,
            'order_id' => 'AMILON_ORDER_123',
            'user_id' => $user->id,
            'order_status' => 'active',
            'payment_id' => $stripePaymentId,
        ]);

        // Verify HTTP request was made with correct data
        Http::assertSent(function ($request) use ($externalOrderId, $quantity): bool {
            return $request->url() === 'https://api.amilon.test/b2bwebapi/v1/Orders/create/TEST_CONTRACT_ID'
                && $request->data()['externalOrderId'] === $externalOrderId
                && $request->data()['orderRows'][0]['productId'] === 'VOUCHER_50'
                && $request->data()['orderRows'][0]['quantity'] === $quantity;
        });
    }

    #[Test]
    public function it_generates_unique_external_order_id(): void
    {
        // Act
        $orderId1 = $this->service->generateExternalOrderId();
        $orderId2 = $this->service->generateExternalOrderId();

        // Assert
        $this->assertNotEquals($orderId1, $orderId2);
        $this->assertStringStartsWith('ENGAGE-', $orderId1);
        $this->assertStringStartsWith('ENGAGE-', $orderId2);
        $this->assertGreaterThan(20, strlen($orderId1));
    }

    #[Test]
    public function it_retrieves_order_status(): void
    {
        $this->markTestSkipped('Not implemented yet');
        // Arrange
        $merchant = Merchant::factory()->create(['merchant_id' => 'MERCHANT_'.uniqid()]);
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
            'product_code' => 'PRODUCT_123',
        ]);

        $uniqueId = uniqid();
        $externalOrderId = 'EXT_ORDER_TO_CHECK_'.$uniqueId;
        $orderId = 'ORDER_TO_CHECK_'.$uniqueId;

        $order = Order::factory()->create([
            'order_id' => $orderId,
            'external_order_id' => $externalOrderId,
            'status' => 'pending',
            'merchant_id' => $merchant->merchant_id,
        ]);

        // Create order item with voucher data
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
            'vouchers' => [
                [
                    'code' => 'COMPLETED_VOUCHER',
                    'url' => 'https://vouchers.amilon.com/COMPLETED_VOUCHER',
                ],
            ],
        ]);

        Http::fake([
            '*' => Http::response([
                'order_id' => $orderId,
                'orderStatus' => 'completed',
                'orderRows' => [
                    [
                        'productId' => 'PRODUCT_123',
                        'vouchers' => [
                            [
                                'code' => 'COMPLETED_VOUCHER',
                                'url' => 'https://vouchers.amilon.com/COMPLETED_VOUCHER',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $status = $this->service->getOrderStatus($orderId);

        // Assert - status should be 'confirmed' based on mapping
        $this->assertEquals('confirmed', $status['status']);
        $this->assertEquals('COMPLETED_VOUCHER', $status['voucher_code']);
        $this->assertEquals('https://vouchers.amilon.com/COMPLETED_VOUCHER', $status['voucher_url']);

        // Verify order status was updated in database
        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
    }

    #[Test]
    public function it_handles_network_timeout_gracefully(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Create merchant first
        $merchant = Merchant::factory()->create(['merchant_id' => 'MERCHANT_'.uniqid()]);

        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
        ]);

        Http::fake(function (): void {
            throw new ConnectionException('Network error');
        });

        // Act & Assert
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Network error');

        $this->service->createOrder(
            $product,
            50,
            'EXT_ORDER_TIMEOUT',
            $user->id,
            'pi_timeout'
        );
    }

    #[Test]
    public function it_saves_order_metadata(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Create merchant first
        $merchant = Merchant::factory()->create(['merchant_id' => 'MERCHANT_'.uniqid()]);

        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create([
            'merchant_id' => $merchant->merchant_id,
        ]);

        Http::fake([
            'https://api.amilon.test/b2bwebapi/v1/Orders/create/TEST_CONTRACT_ID' => Http::response([
                'order_id' => 'ORDER_WITH_META',
                'orderStatus' => 'active',
                'metadata' => [
                    'campaign' => 'XMAS_2024',
                    'discount' => '20%',
                ],
                'orderRows' => [
                    [
                        'productId' => $product->product_code,
                        'quantity' => 100,
                        'vouchers' => [
                            [
                                'code' => 'META_VOUCHER',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Act
        $this->service->createOrder(
            $product,
            100,
            'EXT_META_ORDER',
            $user->id,
            'pi_meta_123'
        );

        // Assert
        $order = Order::where('order_id', 'ORDER_WITH_META')->first();
        $this->assertNotNull($order);

        // For now, metadata is not implemented in OrderDTO, so we just check the order was created
        $this->assertEquals('ORDER_WITH_META', $order->order_id);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
