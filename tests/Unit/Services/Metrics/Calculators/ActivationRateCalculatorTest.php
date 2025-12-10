<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Enums\MetricPeriod;
use App\Enums\User\UserStatus;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\ActivationRateCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class ActivationRateCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ActivationRateCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ActivationRateCalculator;
        $this->financer = Financer::factory()->create();
        // Ensure activeFinancerID() resolves during tests
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::ACTIVATION_RATE,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_activation_rate_for_daily_period(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        // Activation rate is based on invited vs activated users
        $totalInvited = 10;
        $activatedUsers = 6;

        // Create invited users (new architecture with invited_at)
        for ($i = 0; $i < $totalInvited; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom->copy()->addDays(intval(floor($i / 2))),
                'invitation_status' => UserStatus::INVITED,
                'enabled' => $i < $activatedUsers, // First 6 are activated
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = $i < $activatedUsers; // First 6 are active
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom->copy()->addDays(intval(floor($i / 2)));
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('daily', $result);

        $this->assertEquals(60.0, $result['total']); // 6/10 * 100
        // Depending on inclusive boundaries, daily size can be 7 or 8
        $this->assertGreaterThanOrEqual(7, count($result['daily']));
        $this->assertLessThanOrEqual(8, count($result['daily']));
    }

    #[Test]
    public function it_calculates_activation_rate_for_monthly_period(): void
    {
        $dateFrom = Carbon::now()->subDays(30);
        $dateTo = Carbon::now();

        // Simulate invited and registered across the month
        $weeks = [
            ['daysAgo' => 30, 'invited' => 20, 'registered' => 15],
            ['daysAgo' => 20, 'invited' => 15, 'registered' => 10],
            ['daysAgo' => 10, 'invited' => 10, 'registered' => 8],
            ['daysAgo' => 5,  'invited' => 5,  'registered' => 5],
        ];

        foreach ($weeks as $w) {
            $base = Carbon::now()->subDays($w['daysAgo']);

            // Create invited users with new architecture
            for ($i = 0; $i < $w['invited']; $i++) {
                $isActivated = $i < $w['registered'];

                $invitedUser = User::factory()->create([
                    'invited_at' => $base,
                    'invitation_status' => UserStatus::INVITED,
                    'enabled' => $isActivated,
                ]);

                $fu = new FinancerUser;
                $fu->financer_id = $this->financer->id;
                $fu->user_id = $invitedUser->id;
                $fu->active = $isActivated;
                $fu->role = RoleDefaults::BENEFICIARY;
                $fu->created_at = $base;
                $fu->save();
            }
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::THIRTY_DAYS
        );

        $this->assertArrayHasKey('total', $result);
        $expectedTotal = 50; // invited
        $expectedActivated = 38; // registered
        $expectedRate = round(($expectedActivated / $expectedTotal) * 100, 1);

        $this->assertEquals($expectedRate, $result['total']);
        $this->assertIsArray($result['daily']);
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
            'metrics:activation-rate:%s:2025-07-01:2025-07-31:30d',
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
    public function it_handles_zero_users(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        $this->assertEquals(0.0, $result['total']);
        $this->assertIsArray($result['daily']);
    }

    #[Test]
    public function it_handles_all_users_activated(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        // Invite 5 and activate all 5
        for ($i = 0; $i < 5; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom->copy()->addDays($i),
                'invitation_status' => UserStatus::INVITED,
                'enabled' => true, // All activated
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = true; // All active
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom->copy()->addDays($i);
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        $this->assertEquals(100.0, $result['total']);
    }

    #[Test]
    public function it_calculates_daily_activation_trends(): void
    {
        $dateFrom = Carbon::now()->subDays(3);
        $dateTo = Carbon::now();

        // Day 1: invite 2, activate 1 (50%)
        for ($i = 0; $i < 2; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom,
                'invitation_status' => UserStatus::INVITED,
                'enabled' => $i < 1, // Only first one activated
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = $i < 1; // Only first one active
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom->copy();
            $fu->save();
        }

        // Day 2: invite 3, activate 3 (100%)
        for ($i = 0; $i < 3; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom->copy()->addDay(),
                'invitation_status' => UserStatus::INVITED,
                'enabled' => true, // All activated
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = true; // All active
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom->copy()->addDay();
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::CUSTOM
        );

        $daily = $result['daily'];
        // Verify series shape and plausible bounds
        $rates = array_map(fn (array $d): float => $d['count'], $daily);
        $this->assertLessThanOrEqual(100.0, $rates === [] ? 0.0 : max($rates), 'Rates should be <= 100');
        // Ensure non-decreasing cumulative trend
        $isNonDecreasing = true;
        $counter = count($rates);
        for ($i = 1; $i < $counter; $i++) {
            if ($rates[$i] < $rates[$i - 1]) {
                $isNonDecreasing = false;
                break;
            }
        }
        $this->assertTrue($isNonDecreasing, 'Rates should be non-decreasing cumulatively');
    }

    #[Test]
    public function it_excludes_inactive_users_from_calculation(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        // Invite 5 and activate all 5
        for ($i = 0; $i < 5; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom,
                'invitation_status' => UserStatus::INVITED,
                'enabled' => true,
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom;
            $fu->save();
        }

        // Create 3 invited but NOT activated users (should be excluded from activation count)
        for ($i = 0; $i < 3; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom,
                'invitation_status' => UserStatus::INVITED,
                'enabled' => false, // Not activated
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = false; // Inactive
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom;
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        // 5 activated / 8 invited total = 62.5%
        $this->assertEquals(62.5, $result['total']);
    }

    #[Test]
    public function it_handles_yearly_period_with_monthly_breakdown(): void
    {
        $dateFrom = Carbon::now()->subMonths(12);
        $dateTo = Carbon::now();

        // Create invites and activations across the year
        for ($month = 0; $month < 12; $month++) {
            $invited = 12;
            $activated = 8;
            $base = $dateFrom->copy()->addMonths($month)->startOfMonth();

            for ($i = 0; $i < $invited; $i++) {
                $isActivated = $i < $activated;

                $invitedUser = User::factory()->create([
                    'invited_at' => $base->copy()->addDays(min($i, 25)),
                    'invitation_status' => UserStatus::INVITED,
                    'enabled' => $isActivated,
                ]);

                $fu = new FinancerUser;
                $fu->financer_id = $this->financer->id;
                $fu->user_id = $invitedUser->id;
                $fu->active = $isActivated;
                $fu->role = RoleDefaults::BENEFICIARY;
                $fu->created_at = $base->copy()->addDays(min($i, 25));
                $fu->save();
            }
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::TWELVE_MONTHS
        );

        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['daily']);
        // 12 months * 8 activated / 12 months * 12 invited = 96 / 144 = 66.7%
        $expectedRate = round((96 / 144) * 100, 1);
        $this->assertEquals($expectedRate, $result['total']);
    }

    // ==========================================
    // REGRESSION TESTS for User Refactor
    // ==========================================

    #[Test]
    public function it_calculates_rate_with_invited_users_only(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        // Create users with invited_at (new refactor architecture)
        for ($i = 0; $i < 10; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom->copy()->addDays($i % 3),
                'invitation_status' => UserStatus::INVITED,
                'enabled' => $i < 4, // 4 activated
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = $i < 4; // 4 active
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom->copy()->addDays($i % 3);
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        // 4 activated / 10 invited = 40%
        $this->assertEquals(40.0, $result['total']);
    }

    #[Test]
    public function it_excludes_users_without_invitation_from_denominator(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        // Create 5 invited users (3 activated)
        for ($i = 0; $i < 5; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom,
                'invitation_status' => UserStatus::INVITED,
                'enabled' => $i < 3,
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = $i < 3;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom;
            $fu->save();
        }

        // Create 3 direct users (no invitation - should NOT count in denominator)
        for ($i = 0; $i < 3; $i++) {
            $directUser = User::factory()->create([
                'invited_at' => null, // No invitation!
                'invitation_status' => null,
                'enabled' => true,
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $directUser->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom;
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        // Only invited users count: 3 activated / 5 invited = 60%
        // Direct users (3) should NOT affect the calculation
        $this->assertEquals(60.0, $result['total']);
    }

    #[Test]
    public function it_handles_mixed_invited_and_direct_users_correctly(): void
    {
        $dateFrom = Carbon::now()->subDays(7);
        $dateTo = Carbon::now();

        // Scenario: Financer has both invitation workflow AND direct user creation
        // Activation rate should ONLY track invited users

        // 10 invited users, 7 activated
        for ($i = 0; $i < 10; $i++) {
            $invitedUser = User::factory()->create([
                'invited_at' => $dateFrom->copy()->addDays($i % 4),
                'invitation_status' => UserStatus::INVITED,
                'enabled' => $i < 7,
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $invitedUser->id;
            $fu->active = $i < 7;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom->copy()->addDays($i % 4);
            $fu->save();
        }

        // 5 direct users (created without invitation process)
        for ($i = 0; $i < 5; $i++) {
            $directUser = User::factory()->create([
                'invited_at' => null,
                'invitation_status' => null,
                'enabled' => true,
            ]);

            $fu = new FinancerUser;
            $fu->financer_id = $this->financer->id;
            $fu->user_id = $directUser->id;
            $fu->active = true;
            $fu->role = RoleDefaults::BENEFICIARY;
            $fu->created_at = $dateFrom;
            $fu->save();
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $dateFrom,
            $dateTo,
            MetricPeriod::SEVEN_DAYS
        );

        // Only invited users: 7 activated / 10 invited = 70%
        // The 5 direct users do NOT affect activation rate
        $this->assertEquals(70.0, $result['total']);
    }
}
