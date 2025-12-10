<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\Financer;
use App\Services\Metrics\Calculators\VoucherPurchasesCalculator;
use Carbon\Carbon;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('metrics')]
class VoucherPurchasesCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private VoucherPurchasesCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache to ensure test isolation
        Cache::flush();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();
        $this->calculator = new VoucherPurchasesCalculator;
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::VOUCHER_PURCHASES,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_voucher_purchases_volume_by_day(): void
    {
        // Create financer for this test
        $this->financer = ModelFactory::createFinancer();

        // Create users linked to the financer
        $users = [];
        for ($i = 0; $i < 3; $i++) {
            $users[] = ModelFactory::createUser([
                'financers' => [
                    ['financer' => $this->financer, 'active' => true],
                ],
            ]);
        }

        $now = Carbon::now();

        // Create a merchant first to avoid factory issues
        $merchant = Merchant::create([
            'name' => 'Test Merchant',
            'country' => 'BE',
            'merchant_id' => 'TEST1234',
            'description' => 'Test merchant',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        // Create orders for different days
        // Day 1: 2 orders totaling 150€
        Order::create([
            'user_id' => $users[0]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 100.00,
            'created_at' => $now->copy()->startOfDay(),
            'merchant_id' => $merchant->merchant_id,
            'external_order_id' => 'order-1',
            'order_id' => 'order-1',
            'price_paid' => 100.00,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-1',
            'order_date' => $now->copy()->startOfDay(),
            'order_status' => 1,
            'gross_amount' => 100.00,
            'net_amount' => 100.00,
            'total_requested_codes' => 1,
        ]);

        Order::create([
            'user_id' => $users[1]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 50.00,
            'created_at' => $now->copy()->startOfDay()->addHours(2),
            'merchant_id' => $merchant->merchant_id,
            'external_order_id' => 'order-2',
            'order_id' => 'order-2',
            'price_paid' => 50.00,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-2',
            'order_date' => $now->copy()->startOfDay()->addHours(2),
            'order_status' => 1,
            'gross_amount' => 50.00,
            'net_amount' => 50.00,
            'total_requested_codes' => 1,
        ]);

        // Day 2: 1 order for 75€
        Order::create([
            'user_id' => $users[2]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 75.00,
            'created_at' => $now->copy()->addDay()->startOfDay(),
            'merchant_id' => $merchant->merchant_id,
            'external_order_id' => 'order-3',
            'order_id' => 'order-3',
            'price_paid' => 75.00,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-3',
            'order_date' => $now->copy()->addDay()->startOfDay(),
            'order_status' => 1,
            'gross_amount' => 75.00,
            'net_amount' => 75.00,
            'total_requested_codes' => 1,
        ]);

        // Calculate metrics
        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'daily'
        );

        // Total should be 3 purchases (count, not amount)
        $this->assertEquals(3, $result['total']);
        $this->assertCount(2, $result['daily']);

        // Day 1: 2 purchases
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        $this->assertEquals(2, $result['daily'][0]['count']);

        // Day 2: 1 purchase
        $this->assertEquals($now->copy()->addDay()->toDateString(), $result['daily'][1]['date']);
        $this->assertEquals(1, $result['daily'][1]['count']);
    }

    #[Test]
    public function it_only_counts_confirmed_orders(): void
    {
        // Create financer for this test
        $this->financer = ModelFactory::createFinancer();

        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $now = Carbon::now();

        // Create a merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant 2',
            'country' => 'BE',
            'merchant_id' => 'TEST5678',
            'description' => 'Test merchant',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        // Create orders with different statuses
        $baseOrderData = [
            'user_id' => $user->id,
            'merchant_id' => $merchant->merchant_id,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-test',
            'order_date' => $now,
            'order_status' => 1,
            'total_requested_codes' => 1,
            'created_at' => $now,
        ];

        Order::create(array_merge($baseOrderData, [
            'status' => OrderStatus::CONFIRMED,
            'amount' => 100.00,
            'external_order_id' => 'order-conf-1',
            'order_id' => 'order-conf-1',
            'price_paid' => 100.00,
            'gross_amount' => 100.00,
            'net_amount' => 100.00,
        ]));

        Order::create(array_merge($baseOrderData, [
            'status' => OrderStatus::PENDING,
            'amount' => 50.00,
            'external_order_id' => 'order-pend-1',
            'order_id' => 'order-pend-1',
            'price_paid' => 50.00,
            'gross_amount' => 50.00,
            'net_amount' => 50.00,
        ]));

        Order::create(array_merge($baseOrderData, [
            'status' => OrderStatus::ERROR,
            'amount' => 25.00,
            'external_order_id' => 'order-err-1',
            'order_id' => 'order-err-1',
            'price_paid' => 25.00,
            'gross_amount' => 25.00,
            'net_amount' => 25.00,
        ]));

        Order::create(array_merge($baseOrderData, [
            'status' => OrderStatus::CANCELLED,
            'amount' => 30.00,
            'external_order_id' => 'order-canc-1',
            'order_id' => 'order-canc-1',
            'price_paid' => 30.00,
            'gross_amount' => 30.00,
            'net_amount' => 30.00,
        ]));

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count the confirmed order (1 purchase, not amount)
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_returns_zero_when_no_financer_users(): void
    {
        // Create financer for this test
        $financer = ModelFactory::createFinancer();

        $result = $this->calculator->calculate(
            $financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['daily']);
    }

    #[Test]
    public function it_only_counts_orders_from_active_financer_users(): void
    {
        // Create financer for this test
        $this->financer = ModelFactory::createFinancer();

        $activeUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $inactiveUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => false],
            ],
        ]);

        $now = Carbon::now();

        // Create a merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant 3',
            'country' => 'BE',
            'merchant_id' => 'TEST9012',
            'description' => 'Test merchant',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        // Create orders for both users
        Order::create([
            'user_id' => $activeUser->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 100.00,
            'created_at' => $now,
            'merchant_id' => $merchant->merchant_id,
            'external_order_id' => 'order-active-1',
            'order_id' => 'order-active-1',
            'price_paid' => 100.00,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-active-1',
            'order_date' => $now,
            'order_status' => 1,
            'gross_amount' => 100.00,
            'net_amount' => 100.00,
            'total_requested_codes' => 1,
        ]);

        Order::create([
            'user_id' => $inactiveUser->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 50.00,
            'created_at' => $now,
            'merchant_id' => $merchant->merchant_id,
            'external_order_id' => 'order-inactive-1',
            'order_id' => 'order-inactive-1',
            'price_paid' => 50.00,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-inactive-1',
            'order_date' => $now,
            'order_status' => 1,
            'gross_amount' => 50.00,
            'net_amount' => 50.00,
            'total_requested_codes' => 1,
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count active user's order (1 purchase)
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_handles_multiple_orders_on_same_day(): void
    {
        // Clear cache to ensure test isolation
        Cache::flush();

        // Create financer for this test
        $this->financer = ModelFactory::createFinancer();

        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $now = Carbon::now();

        // Create a merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant 4',
            'country' => 'BE',
            'merchant_id' => 'TEST3456',
            'description' => 'Test merchant',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        // Count initial orders to ensure we're starting clean
        $initialOrderCount = Order::where('user_id', $user->id)->count();

        // Create multiple orders on the same day (use startOfDay to ensure all fit in one day)
        $ordersCreated = [];
        for ($i = 0; $i < 5; $i++) {
            $ordersCreated[] = Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::CONFIRMED,
                'amount' => 20.00,
                'created_at' => $now->copy()->startOfDay()->addHours($i * 2), // Space them out within the same day
                'merchant_id' => $merchant->merchant_id,
                'external_order_id' => 'order-multi-'.$i,
                'order_id' => 'order-multi-'.$i,
                'price_paid' => 20.00,
                'voucher_url' => 'https://example.com',
                'payment_id' => 'payment-multi-'.$i,
                'order_date' => $now->copy()->startOfDay()->addHours($i * 2),
                'order_status' => 1,
                'gross_amount' => 20.00,
                'net_amount' => 20.00,
                'total_requested_codes' => 1,
            ]);
        }

        // Verify we actually created 5 orders
        $this->assertCount(5, $ordersCreated);
        $finalOrderCount = Order::where('user_id', $user->id)->count();
        $this->assertEquals($initialOrderCount + 5, $finalOrderCount);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should count all 5 purchases (count, not amount)
        $this->assertEquals(5, $result['total']);
        $this->assertCount(1, $result['daily']);
        $this->assertEquals(5, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_returns_zero_for_days_without_orders(): void
    {
        // Create financer for this test
        $this->financer = ModelFactory::createFinancer();

        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);

        $now = Carbon::now();

        // Create a merchant first
        $merchant = Merchant::create([
            'name' => 'Test Merchant 5',
            'country' => 'BE',
            'merchant_id' => 'TEST7890',
            'description' => 'Test merchant',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        // Create order only on first day
        Order::create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 100.00,
            'created_at' => $now,
            'merchant_id' => $merchant->merchant_id,
            'external_order_id' => 'order-zero-1',
            'order_id' => 'order-zero-1',
            'price_paid' => 100.00,
            'voucher_url' => 'https://example.com',
            'payment_id' => 'payment-zero-1',
            'order_date' => $now,
            'order_status' => 1,
            'gross_amount' => 100.00,
            'net_amount' => 100.00,
            'total_requested_codes' => 1,
        ]);

        // Calculate for 3 days
        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDays(2)->endOfDay(),
            'daily'
        );

        $this->assertEquals(1, $result['total']); // 1 purchase total
        $this->assertCount(3, $result['daily']);

        // First day has the 1 purchase
        $this->assertEquals(1, $result['daily'][0]['count']);

        // Other days should be 0
        $this->assertEquals(0, $result['daily'][1]['count']);
        $this->assertEquals(0, $result['daily'][2]['count']);
    }
}
