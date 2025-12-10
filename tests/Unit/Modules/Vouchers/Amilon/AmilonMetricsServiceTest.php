<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Services\AmilonMetricsService;
use App\Models\User;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
#[FlushTables(
    tables: [
        'int_vouchers_amilon_orders',
        'int_vouchers_amilon_products',
        'int_vouchers_amilon_merchant_category',
        'int_vouchers_amilon_merchants',
        'users',
    ],
    scope: 'test',
    expand: true  // Let the system discover and include dependent tables
)]
class AmilonMetricsServiceTest extends ProtectedRouteTestCase
{
    protected AmilonMetricsService $metricsService;

    protected Carbon $from;

    protected Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();
        // Create the service
        $this->metricsService = new AmilonMetricsService;

        // Default period: last 30 days
        $this->from = now()->subDays(30)->startOfDay();
        $this->to = now()->endOfDay();
    }

    #[Test]
    public function it_converts_eurocents_to_euros_in_metrics(): void
    {
        // Create orders with amounts in EUROCENTS (as stored in DB)
        // 9500 eurocents = 95.00 EUR
        // 10000 eurocents = 100.00 EUR
        resolve(OrderFactory::class)->create([
            'amount' => 9500, // 95.00 EUR in eurocents
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'amount' => 10000, // 100.00 EUR in eurocents
            'created_at' => now()->subDays(10),
        ]);

        // Calculate total purchase volume
        $totalPurchaseVolume = $this->metricsService->calculateTotalPurchaseVolume($this->from, $this->to);

        // Assert the total is converted to EUROS (divide by 100)
        // 9500 + 10000 = 19500 eurocents = 195.00 EUR
        $this->assertEquals(195.00, $totalPurchaseVolume);
    }

    #[Test]
    public function it_calculates_total_purchase_volume(): void
    {
        // Create orders with amounts in EUROCENTS
        resolve(OrderFactory::class)->create([
            'amount' => 5000, // 50.00 EUR
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'amount' => 10000, // 100.00 EUR
            'created_at' => now()->subDays(10),
        ]);

        // Create an order outside the date range
        resolve(OrderFactory::class)->create([
            'amount' => 20000, // 200.00 EUR
            'created_at' => now()->subDays(40),
        ]);

        // Calculate total purchase volume
        $totalPurchaseVolume = $this->metricsService->calculateTotalPurchaseVolume($this->from, $this->to);

        // Assert the total is correct (only orders within the date range), converted to EUROS
        $this->assertEquals(150.00, $totalPurchaseVolume);
    }

    #[Test]
    public function it_calculates_adoption_rate(): void
    {
        // Count existing users before test
        $existingActiveUsers = User::where('enabled', true)->count();

        // Create active users
        $user1 = User::factory()->create(['enabled' => true]);
        $user2 = User::factory()->create(['enabled' => true]);
        User::factory()->create(['enabled' => true]);
        User::factory()->create(['enabled' => true]);

        // Create inactive user
        $inactiveUser = User::factory()->create(['enabled' => false]);

        // Create orders for 2 of the 4 active users (amounts in EUROCENTS)
        resolve(OrderFactory::class)->create([
            'user_id' => $user1->id,
            'amount' => 5000, // 50.00 EUR
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'user_id' => $user2->id,
            'amount' => 10000, // 100.00 EUR
            'created_at' => now()->subDays(10),
        ]);

        // Create an order for the inactive user
        resolve(OrderFactory::class)->create([
            'user_id' => $inactiveUser->id,
            'amount' => 7500, // 75.00 EUR
            'created_at' => now()->subDays(5),
        ]);

        // Calculate adoption rate
        $adoptionRate = $this->metricsService->calculateAdoptionRate($this->from, $this->to);

        // Assert the adoption rate is correct (2 new users with orders out of 4 new active users)
        $expectedTotalUsers = $existingActiveUsers + 4; // 4 new active users created
        $expectedUsersWithPurchases = 2; // Only the 2 new users we created orders for
        $expectedAdoptionRate = ($expectedUsersWithPurchases / $expectedTotalUsers) * 100;

        $this->assertEquals($expectedTotalUsers, $adoptionRate['total_users']);
        $this->assertEquals($expectedUsersWithPurchases, $adoptionRate['users_with_purchases']);
        $this->assertEquals(round($expectedAdoptionRate, 1), round($adoptionRate['adoption_rate'], 1));
    }

    #[Test]
    public function it_calculates_average_amount_per_employee(): void
    {

        // Count existing users before test
        User::where('enabled', true)->count();

        // Create users
        $user1 = User::factory()->create(['enabled' => true]);
        $user2 = User::factory()->create(['enabled' => true]);

        // Create orders for the users (amounts in EUROCENTS)
        resolve(OrderFactory::class)->create([
            'user_id' => $user1->id,
            'amount' => 5000, // 50.00 EUR
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'user_id' => $user2->id,
            'amount' => 10000, // 100.00 EUR
            'created_at' => now()->subDays(10),
        ]);

        // Create another order for user1
        resolve(OrderFactory::class)->create([
            'user_id' => $user1->id,
            'amount' => 2500, // 25.00 EUR
            'created_at' => now()->subDays(5),
        ]);

        // Calculate average amount per employee
        $averageAmount = $this->metricsService->calculateAverageAmountPerEmployee($this->from, $this->to);

        // Assert the average amount is correct
        // Total amount from our created orders: 175.00 EUR (17500 eurocents / 100)
        // Active users WITH PURCHASES in the period: only our 2 new users
        $expectedActiveUsersWithPurchases = 2;

        $this->assertEquals(175.00, $averageAmount['total_amount']);
        $this->assertEquals($expectedActiveUsersWithPurchases, $averageAmount['active_users']);
        $this->assertEquals(87.50, $averageAmount['average_amount']);
    }

    #[Test]
    public function it_counts_vouchers_purchased(): void
    {
        // Create orders
        resolve(OrderFactory::class)->create([
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'created_at' => now()->subDays(10),
        ]);

        resolve(OrderFactory::class)->create([
            'created_at' => now()->subDays(5),
        ]);

        // Create an order outside the date range
        resolve(OrderFactory::class)->create([
            'created_at' => now()->subDays(40),
        ]);

        // Count vouchers purchased
        $vouchersPurchased = $this->metricsService->countVouchersPurchased($this->from, $this->to);

        // Assert the count is correct (only orders within the date range)
        $this->assertEquals(3, $vouchersPurchased);
    }

    #[Test]
    public function it_gets_top_merchants(): void
    {
        // Create merchants
        $merchant1 = resolve(MerchantFactory::class)->create([
            'name' => 'Amazon',
            'merchant_id' => 'AMZN',
        ]);

        $merchant2 = resolve(MerchantFactory::class)->create([
            'name' => 'Carrefour',
            'merchant_id' => 'CRFR',
        ]);

        $merchant3 = resolve(MerchantFactory::class)->create([
            'name' => 'Fnac',
            'merchant_id' => 'FNAC',
        ]);

        // Create orders for the merchants (amounts in EUROCENTS)
        resolve(OrderFactory::class)->create([
            'merchant_id' => $merchant1->merchant_id,
            'amount' => 10000, // 100.00 EUR
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'merchant_id' => $merchant1->merchant_id,
            'amount' => 5000, // 50.00 EUR
            'created_at' => now()->subDays(10),
        ]);

        resolve(OrderFactory::class)->create([
            'merchant_id' => $merchant2->merchant_id,
            'amount' => 7500, // 75.00 EUR
            'created_at' => now()->subDays(5),
        ]);

        resolve(OrderFactory::class)->create([
            'merchant_id' => $merchant3->merchant_id,
            'amount' => 2500, // 25.00 EUR
            'created_at' => now()->subDays(2),
        ]);

        // Get top merchants
        $topMerchants = $this->metricsService->getTopMerchants($this->from, $this->to);

        // Assert the top merchants are correct
        $this->assertCount(3, $topMerchants);

        // First merchant should be Amazon with total amount 150.00 and 2 transactions
        $this->assertEquals('Amazon', $topMerchants[0]->name);
        $this->assertEquals('AMZN', $topMerchants[0]->retailer_id);
        $this->assertEquals(150.00, $topMerchants[0]->total_amount);
        $this->assertEquals(2, $topMerchants[0]->transaction_count);

        // Second merchant should be Carrefour with total amount 75.00 and 1 transaction
        $this->assertEquals('Carrefour', $topMerchants[1]->name);
        $this->assertEquals('CRFR', $topMerchants[1]->retailer_id);
        $this->assertEquals(75.00, $topMerchants[1]->total_amount);
        $this->assertEquals(1, $topMerchants[1]->transaction_count);

        // Third merchant should be Fnac with total amount 25.00 and 1 transaction
        $this->assertEquals('Fnac', $topMerchants[2]->name);
        $this->assertEquals('FNAC', $topMerchants[2]->retailer_id);
        $this->assertEquals(25.00, $topMerchants[2]->total_amount);
        $this->assertEquals(1, $topMerchants[2]->transaction_count);
    }

    #[Test]
    public function it_calculates_all_metrics(): void
    {
        // Create users
        $user1 = User::factory()->create(['enabled' => true]);
        $user2 = User::factory()->create(['enabled' => true]);

        // Create merchants
        $merchant1 = resolve(MerchantFactory::class)->create([
            'name' => 'Amazon',
            'merchant_id' => 'AMZN',
        ]);

        $merchant2 = resolve(MerchantFactory::class)->create([
            'name' => 'Carrefour',
            'merchant_id' => 'CRFR',
        ]);

        // Create orders (amounts in EUROCENTS)
        resolve(OrderFactory::class)->create([
            'user_id' => $user1->id,
            'merchant_id' => $merchant1->merchant_id,
            'amount' => 10000, // 100.00 EUR
            'created_at' => now()->subDays(15),
        ]);

        resolve(OrderFactory::class)->create([
            'user_id' => $user2->id,
            'merchant_id' => $merchant2->merchant_id,
            'amount' => 5000, // 50.00 EUR
            'created_at' => now()->subDays(10),
        ]);

        // Calculate all metrics
        $allMetrics = $this->metricsService->calculateAllMetrics($this->from, $this->to);

        // Assert the metrics are correct
        $this->assertEquals($this->from->toDateString(), $allMetrics['period']['from']);
        $this->assertEquals($this->to->toDateString(), $allMetrics['period']['to']);
        $this->assertEquals(150.00, $allMetrics['total_purchase_volume']);
        $this->assertEquals(2, $allMetrics['adoption_rate']['total_users']);
        $this->assertEquals(2, $allMetrics['adoption_rate']['users_with_purchases']);
        $this->assertEquals(100.0, $allMetrics['adoption_rate']['adoption_rate']);
        $this->assertEquals(150.00, $allMetrics['average_amount_per_employee']['total_amount']);
        $this->assertEquals(2, $allMetrics['average_amount_per_employee']['active_users']);
        $this->assertEquals(75.00, $allMetrics['average_amount_per_employee']['average_amount']);
        $this->assertEquals(2, $allMetrics['vouchers_purchased']);
        $this->assertCount(2, $allMetrics['top_merchants']);
    }

    #[Test]
    public function it_returns_zero_values_when_no_data(): void
    {
        // Calculate metrics with no data
        $totalPurchaseVolume = $this->metricsService->calculateTotalPurchaseVolume($this->from, $this->to);
        $adoptionRate = $this->metricsService->calculateAdoptionRate($this->from, $this->to);
        $averageAmount = $this->metricsService->calculateAverageAmountPerEmployee($this->from, $this->to);
        $vouchersPurchased = $this->metricsService->countVouchersPurchased($this->from, $this->to);
        $topMerchants = $this->metricsService->getTopMerchants($this->from, $this->to);

        // Assert the metrics return zero values
        $this->assertEquals(0, $totalPurchaseVolume);
        $this->assertEquals(0, $adoptionRate['adoption_rate']);
        $this->assertEquals(0, $averageAmount['average_amount']);
        $this->assertEquals(0, $vouchersPurchased);
        $this->assertCount(0, $topMerchants);
    }
}
