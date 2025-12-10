<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Enums\MetricPeriod;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\ActiveBeneficiariesCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class ActiveBeneficiariesCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ActiveBeneficiariesCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ActiveBeneficiariesCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::ACTIVE_BENEFICIARIES,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_active_beneficiaries_for_daily_period(): void
    {
        // Create active users
        $activeUsers = User::factory()->count(5)->create();
        foreach ($activeUsers as $user) {
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::now()->subDays(10);
            $fu->save();
        }

        // Create inactive users (should not be counted)
        $inactiveUsers = User::factory()->count(3)->create();
        foreach ($inactiveUsers as $user) {
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = false;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::now()->subDays(10);
            $fu->save();
        }

        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('daily', $result);
        $this->assertEquals(5, $result['total']);
        $this->assertCount(8, $result['daily']); // 7 days + today = 8
    }

    #[Test]
    public function it_calculates_active_beneficiaries_for_monthly_period(): void
    {
        // Create users at different times
        $users = [
            ...User::factory()->count(5)->create(),
            ...User::factory()->count(3)->create(),
            ...User::factory()->count(2)->create(),
        ];

        foreach ($users as $index => $user) {
            $daysAgo = $index < 5 ? 35 : ($index < 8 ? 15 : 5);
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::now()->subDays($daysAgo);
            $fu->save();
        }

        $dateFrom = Carbon::now()->subDays(30);
        $dateTo = Carbon::now();

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::THIRTY_DAYS
        );

        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(10, $result['total']);
    }

    #[Test]
    public function it_generates_correct_cache_key(): void
    {
        $dateFrom = Carbon::parse('2025-07-01');
        $dateTo = Carbon::parse('2025-07-31');

        $cacheKey = $this->calculator->getCacheKey(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::THIRTY_DAYS
        );

        $expectedKey = sprintf(
            'metrics:active-beneficiaries:%s:2025-07-01:2025-07-31:30d',
            $this->financer->id
        );

        $this->assertEquals($expectedKey, $cacheKey);
    }

    #[Test]
    public function it_returns_correct_cache_ttl(): void
    {
        $this->assertEquals(3600, $this->calculator->getCacheTTL());
    }

    #[Test]
    public function it_supports_aggregation(): void
    {
        $this->assertTrue($this->calculator->supportsAggregation());
    }

    #[Test]
    public function it_handles_no_active_users(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        $this->assertEquals(0, $result['total']);
        // With no users, implementation returns empty daily array
        $this->assertIsArray($result['daily']);
        $this->assertCount(0, $result['daily']);
    }

    #[Test]
    public function it_excludes_users_created_after_end_date(): void
    {
        // Create users before the period
        $oldUsers = User::factory()->count(3)->create();
        foreach ($oldUsers as $user) {
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::now()->subDays(20);
            $fu->save();
        }

        // Create users after the period end date
        $futureUsers = User::factory()->count(2)->create();
        foreach ($futureUsers as $user) {
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::now()->addDays(5);
            $fu->save();
        }

        $dateFrom = Carbon::now()->subDays(30);
        $dateTo = Carbon::now()->subDays(10); // End date is 10 days ago

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::CUSTOM
        );

        $this->assertEquals(3, $result['total']); // Only old users
    }

    #[Test]
    public function it_calculates_daily_breakdown_correctly(): void
    {
        // Create users on specific days
        $entries = [
            ['date' => Carbon::now()->subDays(5), 'count' => 2],
            ['date' => Carbon::now()->subDays(3), 'count' => 3],
            ['date' => Carbon::now()->subDays(1), 'count' => 1],
            ['date' => Carbon::now(), 'count' => 4],
        ];

        foreach ($entries as $entry) {
            $users = User::factory()->count($entry['count'])->create();
            foreach ($users as $user) {
                $fu = new FinancerUser;
                $fu->financer_id = $this->financer->id;
                $fu->user_id = $user->id;
                $fu->active = true;
                $fu->role = RoleDefaults::BENEFICIARY;
                $fu->created_at = $entry['date'];
                $fu->save();
            }
        }

        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        $this->assertEquals(10, $result['total']);

        // Check cumulative counts in daily breakdown
        $dailyBreakdown = $result['daily'];
        $lastDayData = end($dailyBreakdown);
        $this->assertEquals(10, $lastDayData['count']); // Total cumulative count
    }

    #[Test]
    public function it_handles_custom_period_with_growth_calculation(): void
    {
        // Users at start
        $startUsers = User::factory()->count(10)->create();
        foreach ($startUsers as $user) {
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::parse('2025-01-01');
            $fu->save();
        }

        // Users added during period
        $newUsers = User::factory()->count(15)->create();
        foreach ($newUsers as $user) {
            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $user->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = Carbon::parse('2025-06-15');
            $fu->save();
        }

        $dateFrom = Carbon::parse('2025-06-01');
        $dateTo = Carbon::parse('2025-06-30');

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::CUSTOM
        );

        $this->assertEquals(25, $result['total']);
        $this->assertIsArray($result['daily']);
        $this->assertGreaterThan(0, count($result['daily']));
    }
}
