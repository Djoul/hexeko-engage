<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\VoucherAverageAmountCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class VoucherAverageAmountCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private VoucherAverageAmountCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new VoucherAverageAmountCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::VOUCHER_AVERAGE_AMOUNT,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_average_voucher_amount_by_day(): void
    {
        // Create users
        $users = User::factory()->count(2)->create();
        foreach ($users as $user) {
            FinancerUser::create([
                'financer_id' => $this->financer->id,
                'user_id' => $user->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $now = Carbon::now();

        // Day 1: 3 orders with amounts 100, 50, 150 (average = 100)
        Order::factory()->create([
            'user_id' => $users[0]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 100.00,
            'created_at' => $now->copy()->startOfDay(),
        ]);

        Order::factory()->create([
            'user_id' => $users[1]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 50.00,
            'created_at' => $now->copy()->startOfDay()->addHours(2),
        ]);

        Order::factory()->create([
            'user_id' => $users[0]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 150.00,
            'created_at' => $now->copy()->startOfDay()->addHours(4),
        ]);

        // Day 2: 2 orders with amounts 80, 120 (average = 100)
        Order::factory()->create([
            'user_id' => $users[1]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 80.00,
            'created_at' => $now->copy()->addDay()->startOfDay(),
        ]);

        Order::factory()->create([
            'user_id' => $users[0]->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 120.00,
            'created_at' => $now->copy()->addDay()->startOfDay()->addHours(3),
        ]);

        // Calculate metrics
        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'daily'
        );

        // Total average should be (100+50+150+80+120) / 5 = 100
        $this->assertEquals(100.0, $result['total']);
        $this->assertCount(2, $result['daily']);

        // Day 1: average = 100
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        $this->assertEquals(100.0, $result['daily'][0]['count']);

        // Day 2: average = 100
        $this->assertEquals($now->copy()->addDay()->toDateString(), $result['daily'][1]['date']);
        $this->assertEquals(100.0, $result['daily'][1]['count']);
    }

    #[Test]
    public function it_only_counts_confirmed_orders(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create orders with different statuses
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 100.00,
            'created_at' => $now,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 200.00,
            'created_at' => $now,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::PENDING,
            'amount' => 50.00,
            'created_at' => $now,
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::ERROR,
            'amount' => 75.00,
            'created_at' => $now,
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count confirmed orders: (100 + 200) / 2 = 150
        $this->assertEquals(150.0, $result['total']);
        $this->assertEquals(150.0, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_returns_zero_when_no_orders(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertCount(1, $result['daily']);
        $this->assertEquals(0, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_returns_zero_when_no_financer_users(): void
    {
        $result = $this->calculator->calculate(
            $this->financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['daily']);
    }

    #[Test]
    public function it_handles_single_order_per_day(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create single order
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::CONFIRMED,
            'amount' => 7550,
            'created_at' => $now,
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Average of single order is the order amount itself
        $this->assertEquals(7550, $result['total']);
        $this->assertEquals(7550, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_calculates_correct_average_for_different_amounts(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create orders with different amounts
        $amounts = [1000, 2550, 10000, 20000, 5000];
        foreach ($amounts as $amount) {
            Order::factory()->create([
                'user_id' => $user->id,
                'status' => OrderStatus::CONFIRMED,
                'amount' => $amount,
                'created_at' => $now,
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Average = (10 + 25.5 + 100 + 200 + 50) / 5 = 77.1
        $this->assertEquals(7710, $result['total']);
        $this->assertEquals(7710, $result['daily'][0]['count']);
    }
}
