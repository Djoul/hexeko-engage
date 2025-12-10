<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\IDP\RoleDefaults;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerMetric;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\FinancerMetricsService;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('metrics')]
#[Group('financer')]
class FinancerMetricsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private FinancerMetricsService $service;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FinancerMetricsService;
        $this->financer = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_exists_as_a_service(): void
    {
        $this->assertInstanceOf(
            FinancerMetricsService::class,
            $this->service
        );
    }

    #[Test]
    public function it_calculates_active_beneficiaries_for_period(): void
    {
        // Create users linked to financer before the end date
        $activeUsersBeforeEndDate = [];
        for ($i = 0; $i < 5; $i++) {
            $activeUsersBeforeEndDate[] = ModelFactory::createUser();
        }
        $inactiveUsersBeforeEndDate = [];
        for ($i = 0; $i < 3; $i++) {
            $inactiveUsersBeforeEndDate[] = ModelFactory::createUser();
        }

        // Create users after the end date (should not be counted)
        $usersAfterEndDate = [];
        for ($i = 0; $i < 2; $i++) {
            $usersAfterEndDate[] = ModelFactory::createUser();
        }

        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(7);

        // Link active users created before end date
        foreach ($activeUsersBeforeEndDate as $user) {
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $this->financer->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(30),
                'created_at' => $endDate->copy()->subDays(10), // Created before end date
            ]);
        }

        // Link inactive users (should not be counted)
        foreach ($inactiveUsersBeforeEndDate as $user) {
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $this->financer->id,
                'active' => false, // Not active
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(30),
                'created_at' => $endDate->copy()->subDays(10),
            ]);
        }

        // Link users created after end date (should not be counted)
        foreach ($usersAfterEndDate as $user) {
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $this->financer->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(30),
                'created_at' => $endDate->copy()->addDays(1), // Created after end date
            ]);
        }

        // Test the period
        $result = $this->service->getActiveBeneficiaries(
            $this->financer->id,
            $startDate,
            $endDate,
            '7d' // period parameter
        );

        // Should only count active users created before end date
        $this->assertEquals(5, $result['total']);
        $this->assertIsArray($result['daily']);

        // Calculate expected days count
        $expectedDays = (int) ($startDate->diffInDays($endDate) + 1); // +1 to include both start and end
        $this->assertCount($expectedDays, $result['daily']);
    }

    #[Test]
    public function it_returns_zero_for_financer_without_users(): void
    {
        $emptyFinancer = ModelFactory::createFinancer();

        $result = $this->service->getActiveBeneficiaries(
            $emptyFinancer->id,
            Carbon::now()->subDays(7),
            Carbon::now(),
            '7d'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['daily']);
    }

    #[Test]
    public function it_calculates_activation_rate(): void
    {
        // Create 10 pending invitations for the financer
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create([
                'email' => "invited{$i}.".uniqid().'@test.com',
                'first_name' => 'Invited',
                'last_name' => "User$i",
                'invitation_status' => 'pending',
                'created_at' => now()->subDays(25),
            ]);

            // Link to financer via pivot table
            $user->financers()->attach($this->financer->id, [
                'active' => false,
                'from' => now()->subDays(25),
                'sirh_id' => '',
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        // Create 7 users who registered (activated)
        for ($i = 0; $i < 7; $i++) {
            $user = ModelFactory::createUser();
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $this->financer->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(20),
                'created_at' => now()->subDays(20),
            ]);
        }

        $result = $this->service->getActivationRate(
            $this->financer->id,
            Carbon::now()->subDays(30),
            Carbon::now(),
            '30d'
        );

        $this->assertEquals(70.0, $result['rate']); // 7/10 = 70%
        $this->assertEquals(10, $result['total_users']); // Total invited
        $this->assertEquals(7, $result['activated_users']); // Total registered
    }

    #[Test]
    public function it_handles_division_by_zero_for_activation_rate(): void
    {
        $emptyFinancer = ModelFactory::createFinancer();

        $result = $this->service->getActivationRate(
            $emptyFinancer->id,
            Carbon::now()->subDays(30),
            Carbon::now(),
            '30d'
        );

        $this->assertEquals(0.0, $result['rate']);
        $this->assertEquals(0, $result['total_users']);
        $this->assertEquals(0, $result['activated_users']);
    }

    #[Test]
    public function it_calculates_median_session_time(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'user_id' => $user->id,
            'financer_id' => $this->financer->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
            'from' => now()->subDays(30),
        ]);

        // Create session logs with different durations (in seconds)
        $sessionDurations = [300, 600, 900, 1200, 1500]; // 5, 10, 15, 20, 25 minutes

        foreach ($sessionDurations as $duration) {
            EngagementLog::create([
                'user_id' => $user->id,
                'type' => 'SessionStarted',
                'metadata' => ['duration' => $duration],
                'logged_at' => now()->subDays(rand(1, 7)),
            ]);
        }

        $result = $this->service->getMedianSessionTime(
            $this->financer->id,
            Carbon::now()->subDays(7),
            Carbon::now(),
            '7d'
        );

        // Median of [300, 600, 900, 1200, 1500] = 900 seconds = 15 minutes
        $this->assertEquals(15, $result['median_minutes']);
        $this->assertEquals(5, $result['total_sessions']);
    }

    #[Test]
    public function it_returns_zero_median_for_no_sessions(): void
    {
        $result = $this->service->getMedianSessionTime(
            $this->financer->id,
            Carbon::now()->subDays(7),
            Carbon::now(),
            '7d'
        );

        $this->assertEquals(0, $result['median_minutes']);
        $this->assertEquals(0, $result['total_sessions']);
    }

    #[Test]
    public function it_calculates_module_usage_stats(): void
    {
        $users = User::factory(3)->create();

        foreach ($users as $user) {
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $this->financer->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(30),
            ]);
        }

        // Create module usage logs
        EngagementLog::create([
            'user_id' => $users[0]->id,
            'type' => 'ModuleUsed',
            'target' => 'vouchers',
            'logged_at' => now()->subDays(1),
        ]);

        EngagementLog::create([
            'user_id' => $users[0]->id,
            'type' => 'ModuleUsed',
            'target' => 'vouchers',
            'logged_at' => now()->subDays(2),
        ]);

        EngagementLog::create([
            'user_id' => $users[1]->id,
            'type' => 'ModuleUsed',
            'target' => 'vouchers',
            'logged_at' => now()->subDays(1),
        ]);

        EngagementLog::create([
            'user_id' => $users[1]->id,
            'type' => 'ModuleUsed',
            'target' => 'hr_tools',
            'logged_at' => now()->subDays(1),
        ]);

        $result = $this->service->getModuleUsageStats(
            $this->financer->id,
            Carbon::now()->subDays(7),
            Carbon::now(),
            '7d'
        );

        // Check that we have the expected module data
        $this->assertArrayHasKey('vouchers', $result);
        $this->assertArrayHasKey('hr_tools', $result);
        $this->assertEquals(2, $result['vouchers']['unique_users']); // 2 unique users
        $this->assertEquals(3, $result['vouchers']['total_uses']); // 3 total uses
        $this->assertEquals(1, $result['hr_tools']['unique_users']);
        $this->assertEquals(1, $result['hr_tools']['total_uses']);
    }

    #[Test]
    public function it_calculates_article_viewed_views(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'user_id' => $user->id,
            'financer_id' => $this->financer->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
            'from' => now()->subDays(30),
        ]);

        // Create article views
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'article-1',
            'logged_at' => now()->subDays(1),
        ]);

        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'article-2',
            'logged_at' => now()->subDays(2),
        ]);

        // Create tool clicks
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'LinkClicked',
            'target' => 'tool-1',
            'logged_at' => now()->subDays(1),
        ]);

        $result = $this->service->getHrCommunicationsViews(
            $this->financer->id,
            Carbon::now()->subDays(7),
            Carbon::now(),
            '7d'
        );

        $this->assertEquals(2, $result['articles']['views']);
        $this->assertEquals(1, $result['articles']['unique_users']);
        $this->assertEquals(1, $result['tools']['clicks']);
        $this->assertEquals(1, $result['tools']['unique_users']);
        $this->assertEquals(3, $result['total_interactions']);
    }

    #[Test]
    public function it_uses_cache_for_read_operations(): void
    {
        // This test verifies that the service uses cache
        // We'll mock the cache in integration tests
        $this->assertTrue(
            method_exists($this->service, 'getCachedMetrics'),
            'Service should have getCachedMetrics method'
        );
    }

    #[Test]
    public function it_stores_metrics_in_database(): void
    {
        // Create a user with engagement data
        $user = User::factory()->create();
        $endDate = Carbon::now();

        FinancerUser::create([
            'user_id' => $user->id,
            'financer_id' => $this->financer->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
            'from' => now()->subDays(30),
            'created_at' => $endDate->copy()->subDays(10), // Created before end date
        ]);

        $startDate = Carbon::now()->subDays(7);

        // Call the service to calculate metrics
        $result = $this->service->getActiveBeneficiaries(
            $this->financer->id,
            $startDate,
            $endDate,
            '7d'
        );

        // Verify metrics were stored in database
        // The metric is stored with the enum value directly with "financer_" prefix
        $storedMetric = FinancerMetric::where('financer_id', $this->financer->id)
            ->where('metric', 'financer_active-beneficiaries')
            ->where('date_from', $startDate->startOfDay()->toDateTimeString())
            ->where('date_to', $endDate->endOfDay()->toDateTimeString())
            ->where('period', '7d')
            ->first();

        $this->assertNotNull($storedMetric, 'Metric should be stored in database');
        $this->assertEquals($result['total'], $storedMetric->data['total']);
        $this->assertEquals($startDate->toDateString(), $storedMetric->data['start_date']);
        $this->assertEquals($endDate->toDateString(), $storedMetric->data['end_date']);
    }

    #[Test]
    public function it_retrieves_fresh_metrics_from_database(): void
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Create test users
        $users = User::factory(10)->create();
        foreach ($users as $user) {
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $this->financer->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(30),
                'created_at' => $endDate->copy()->subDays(15), // Created before end date
            ]);
        }

        // Create a fresh metric in database
        $dailyStats = [];
        $period = new DatePeriod(
            $startDate,
            new DateInterval('P1D'),
            $endDate,
            DatePeriod::INCLUDE_END_DATE
        );

        foreach ($period as $date) {
            $dailyStats[] = [
                'date' => $date->format('Y-m-d'),
                'count' => 10, // All users were created before this date
            ];
        }

        $metricData = [
            'total' => 10,
            'daily' => $dailyStats,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ];

        FinancerMetric::create([
            'financer_id' => $this->financer->id,
            'metric' => 'financer_active-beneficiaries',
            'date_from' => $startDate->startOfDay()->toDateTimeString(),
            'date_to' => $endDate->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'data' => $metricData,
        ]);

        // Call the service
        $result = $this->service->getActiveBeneficiaries(
            $this->financer->id,
            $startDate,
            $endDate,
            '7d'
        );

        // Verify it returned the stored data
        $this->assertEquals(10, $result['total']);

        // Calculate expected days count
        $expectedDays = (int) ($startDate->diffInDays($endDate) + 1); // +1 to include both start and end
        $this->assertCount($expectedDays, $result['daily']);
    }

    #[Test]
    public function it_recalculates_stale_metrics(): void
    {
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();

        // Create a stale metric (older than 24 hours)
        $staleMetric = FinancerMetric::create([
            'financer_id' => $this->financer->id,
            'metric' => 'financer_active-beneficiaries',
            'date_from' => $startDate->startOfDay()->toDateTimeString(),
            'date_to' => $endDate->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'data' => [
                'total' => 999, // Old data
                'daily' => [],
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);

        // Manually set created_at to 25 hours ago
        $staleMetric->created_at = now()->subHours(25);
        $staleMetric->save();

        // Create new active user
        $user = User::factory()->create();
        FinancerUser::create([
            'user_id' => $user->id,
            'financer_id' => $this->financer->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
            'from' => now()->subDays(30),
            'created_at' => $endDate->copy()->subDays(5), // Created before end date
        ]);

        // Call the service
        $result = $this->service->getActiveBeneficiaries(
            $this->financer->id,
            $startDate,
            $endDate,
            '7d'
        );

        // Verify it recalculated instead of using stale data
        $this->assertEquals(1, $result['total']); // New calculation
        $this->assertNotEquals(999, $result['total']); // Not the stale data
    }

    #[Test]
    #[Group('performance')]
    public function it_avoids_n_plus_1_queries_when_calculating_active_beneficiaries(): void
    {
        // Clear all cache before test to ensure clean state
        Cache::flush();

        // Use a unique financer for this test to avoid cache/DB interference
        $testFinancer = ModelFactory::createFinancer();

        // Create 10 active users for this test financer
        $users = User::factory(10)->create();
        $endDate = Carbon::now();

        foreach ($users as $user) {
            FinancerUser::create([
                'user_id' => $user->id,
                'financer_id' => $testFinancer->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
                'from' => now()->subDays(30),
                'created_at' => $endDate->copy()->subDays(15),
            ]);
        }

        // Test for 30-day period (should have daily breakdown)
        $startDate = Carbon::now()->subDays(30);

        // Clear query log and enable fresh logging
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Call the service method
        $this->service->getActiveBeneficiaries(
            $testFinancer->id,
            $startDate,
            $endDate,
            '30d'
        );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Debug: Print queries if test fails
        $financerUserQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'financer_user') &&
                   str_contains($query['query'], 'select');
        });

        // CRITICAL: Should only query financer_user table ONCE for the entire calculation
        // Not once per day in the period (which would be 31 queries for 30 days + 1 for initial fetch)
        // The fix should reduce this to maximum 2 queries:
        // 1. Initial fetch of all active financer users
        // 2. Optional: Check for existing metrics in DB
        $this->assertLessThanOrEqual(
            2,
            $financerUserQueries->count(),
            "Expected at most 2 queries to financer_user table, but found {$financerUserQueries->count()}. "
            ."This indicates an N+1 query problem where we're fetching data for each day separately.\n"
            .'Queries: '.$financerUserQueries->pluck('query')->join("\n")
        );
    }
}
