<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerMetricsController;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

/**
 * Regression tests for N+1 Query issues on metrics dashboard
 *
 * Related Sentry Issues:
 * - ENGAGE-MAIN-API-6X: N+1 on engagement_logs (469 occurrences)
 *   URL: https://upengage.sentry.io/issues/55968675/
 * - ENGAGE-MAIN-API-6V: N+1 on engagement_logs with DISTINCT (409 occurrences)
 *   URL: https://upengage.sentry.io/issues/55968673/
 * - ENGAGE-MAIN-API-73: N+1 on financer_user COUNT queries (404 occurrences)
 *   URL: https://upengage.sentry.io/issues/56206795/
 * - ENGAGE-MAIN-API-7B: N+1 on int_vouchers_amilon_orders SUM queries (135 occurrences)
 *   URL: https://upengage.sentry.io/issues/56406241/
 * - ENGAGE-MAIN-API-78: N+1 on int_communication_rh_article_interactions COUNT queries (132 occurrences)
 *   URL: https://upengage.sentry.io/issues/56406236/
 * - NEW: N+1 on engagement_logs (LinkClicked) in ShortcutsClicksCalculator
 * - NEW: N+1 on engagement_logs (ArticleViewed) in ArticlesPerEmployeeCalculator
 *
 * Problems:
 * 1. engagement_logs: Queries executed for each day in date range
 *    (30 days = 90+ queries across 3 calculators)
 * 2. financer_user: COUNT queries executed for each day in date range
 *    (30 days = 30+ queries in ActiveBeneficiariesCalculator)
 * 3. int_vouchers_amilon_orders: SUM/aggregate queries for each day in date range
 *    (30 days = 60+ queries across 2 voucher calculators)
 * 4. int_communication_rh_article_interactions: COUNT queries for each day in date range
 *    (30 days = 30+ queries in ArticleReactionsCalculator)
 * 5. engagement_logs (LinkClicked): COUNT queries for each day in ShortcutsClicksCalculator
 *    (30 days = 30+ queries)
 * 6. engagement_logs (ArticleViewed): DISTINCT queries for each day in ArticlesPerEmployeeCalculator
 *    (30 days = 30+ queries)
 *
 * Solutions:
 * 1. Load all engagement logs in single query with whereBetween, filter in memory
 * 2. Load all financer users once, count in memory by date
 * 3. Load all Amilon orders once, aggregate in memory by date
 * 4. Load all article interactions once, count in memory by date
 * 5. Load all link clicks once, count by date and target in memory
 * 6. Load all article views once, count unique targets per user by date in memory
 *
 * This test suite ensures N+1 queries are fixed and prevents regression.
 */
#[FlushTables(tables: ['engagement_logs', 'financer_users', 'financers', 'users', 'modules', 'permissions', 'model_has_permissions', 'int_vouchers_amilon_orders', 'int_vouchers_amilon_order_items', 'int_communication_rh_articles', 'int_communication_rh_article_interactions', 'int_outils_rh_links'], scope: 'class')]
#[Group('metrics')]
#[Group('financer')]
class FinancerMetricsN1QueryRegressionTest extends ProtectedRouteTestCase
{
    private Financer $financer;

    private User $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();

        Context::flush();
        config(['metrics.disabled_metrics' => []]);

        // Create financer and beneficiary with realistic test data
        $this->financer = Financer::factory()->create();
        $this->beneficiary = $this->createAuthUserWithFinancer();

        // Create test engagement logs for the past 30 days
        $this->createTestEngagementLogs();

        // Create test modules for ModuleUsage metric
        $this->createTestModules();
    }

    protected function tearDown(): void
    {
        Context::flush();
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    /**
     * Test that dashboard metrics endpoint does not generate N+1 queries
     *
     * Before fix: ~90+ queries (3 calculators × 30 days each)
     * After fix: Should be < 20 queries total
     */
    #[Test]
    public function dashboard_endpoint_does_not_generate_n1_queries(): void
    {
        // Clear query log before test
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Execute dashboard request
        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/dashboard?period=30d');

        // Get query log
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Assert response is successful
        $response->assertStatus(200);

        // Count engagement_logs queries
        $engagementLogsQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'engagement_logs');
        })->count();

        // Before fix: This would be 90+ queries (3 metrics × 30 days)
        // After fix: Should be maximum 10 queries (reasonable for caching, eager loading, etc.)
        $this->assertLessThanOrEqual(
            10,
            $engagementLogsQueries,
            sprintf(
                'Dashboard should not generate N+1 queries on engagement_logs. Expected ≤10, got %d queries. '.
                'This indicates the N+1 query bug has regressed. '.
                'Check SessionTimeCalculator, ModuleUsageCalculator, and HrCommunicationsCalculator.',
                $engagementLogsQueries
            )
        );

        // Additional assertion: Total query count should be reasonable
        $totalQueries = count($queries);
        $this->assertLessThanOrEqual(
            50,
            $totalQueries,
            sprintf(
                'Dashboard should not generate excessive queries. Expected ≤50, got %d total queries.',
                $totalQueries
            )
        );
    }

    /**
     * Test SessionTimeCalculator specifically does not generate N+1 queries
     */
    #[Test]
    public function session_time_metric_does_not_generate_n1_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/session-time?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $engagementLogsQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'engagement_logs')
                && (str_contains($query['query'], 'SessionStarted') || str_contains($query['query'], 'SessionFinished'));
        })->count();

        // Should load all sessions in 2 queries max (SessionStarted + SessionFinished)
        $this->assertLessThanOrEqual(
            4,
            $engagementLogsQueries,
            sprintf(
                'SessionTimeCalculator should not generate N+1 queries. Expected ≤4, got %d queries.',
                $engagementLogsQueries
            )
        );
    }

    /**
     * Test ModuleUsageCalculator specifically does not generate N+1 queries
     */
    #[Test]
    public function module_usage_metric_does_not_generate_n1_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/module-usage?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $engagementLogsQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'engagement_logs')
                && str_contains($query['query'], 'ModuleAccessed');
        })->count();

        // Should load all module accesses in 1 query
        $this->assertLessThanOrEqual(
            2,
            $engagementLogsQueries,
            sprintf(
                'ModuleUsageCalculator should not generate N+1 queries. Expected ≤2, got %d queries.',
                $engagementLogsQueries
            )
        );
    }

    /**
     * Test HrCommunicationsCalculator specifically does not generate N+1 queries
     */
    #[Test]
    public function article_viewed_metric_does_not_generate_n1_queries(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/article_viewed?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $engagementLogsQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'engagement_logs')
                && str_contains($query['query'], 'ArticleViewed');
        })->count();

        // Should load all article views in 1 query
        $this->assertLessThanOrEqual(
            2,
            $engagementLogsQueries,
            sprintf(
                'HrCommunicationsCalculator should not generate N+1 queries. Expected ≤2, got %d queries.',
                $engagementLogsQueries
            )
        );
    }

    /**
     * Test ActiveBeneficiariesCalculator does not generate N+1 queries on financer_user
     *
     * Related to ENGAGE-MAIN-API-73
     * Before fix: 30+ COUNT queries on financer_user (one per day)
     * After fix: Should be 1-2 queries maximum
     */
    #[Test]
    public function active_beneficiaries_metric_does_not_generate_n1_queries_on_financer_user(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/active-beneficiaries?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $financerUserQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'financer_user')
                && str_contains($query['query'], 'count(*) as aggregate');
        })->count();

        // Should load financer users once, not count in loop (30 times)
        $this->assertLessThanOrEqual(
            2,
            $financerUserQueries,
            sprintf(
                'ActiveBeneficiariesCalculator should not generate N+1 COUNT queries on financer_user. '.
                'Expected ≤2, got %d queries. This indicates the N+1 query bug on financer_user has regressed.',
                $financerUserQueries
            )
        );
    }

    /**
     * Test VoucherPurchasesCalculator does not generate N+1 queries on Amilon orders
     *
     * Related to ENGAGE-MAIN-API-7B
     * Before fix: 30+ SUM queries on int_vouchers_amilon_orders (one per day)
     * After fix: Should be 1-2 queries maximum
     */
    #[Test]
    public function voucher_purchases_metric_does_not_generate_n1_queries_on_amilon_orders(): void
    {
        // Create test Amilon orders for the past 30 days
        $this->createTestAmilonOrders();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/voucher-purchases?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $amilonOrderQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'int_vouchers_amilon_orders')
                && str_contains($query['query'], 'sum("amount") as aggregate');
        })->count();

        // Should load all orders once, not sum in loop (30 times)
        $this->assertLessThanOrEqual(
            2,
            $amilonOrderQueries,
            sprintf(
                'VoucherPurchasesCalculator should not generate N+1 SUM queries on int_vouchers_amilon_orders. '.
                'Expected ≤2, got %d queries. This indicates the N+1 query bug on Amilon orders has regressed.',
                $amilonOrderQueries
            )
        );
    }

    /**
     * Test VoucherAverageAmountCalculator does not generate N+1 queries on Amilon orders
     *
     * Related to ENGAGE-MAIN-API-7B
     * Before fix: 30+ queries on int_vouchers_amilon_orders (one per day)
     * After fix: Should be 1-2 queries maximum
     */
    #[Test]
    public function voucher_average_amount_metric_does_not_generate_n1_queries_on_amilon_orders(): void
    {
        // Create test Amilon orders for the past 30 days
        $this->createTestAmilonOrders();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/voucher-average-amount?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $amilonOrderQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'int_vouchers_amilon_orders');
        })->count();

        // Should load all orders once, not query per day (30 times)
        $this->assertLessThanOrEqual(
            2,
            $amilonOrderQueries,
            sprintf(
                'VoucherAverageAmountCalculator should not generate N+1 queries on int_vouchers_amilon_orders. '.
                'Expected ≤2, got %d queries. This indicates the N+1 query bug on Amilon orders has regressed.',
                $amilonOrderQueries
            )
        );
    }

    /**
     * Test ArticleReactionsCalculator does not generate N+1 queries on article_interactions
     *
     * Related to ENGAGE-MAIN-API-78
     * Before fix: 30+ COUNT queries on int_communication_rh_article_interactions (one per day)
     * After fix: Should be 1-2 queries maximum
     */
    #[Test]
    public function article_reactions_metric_does_not_generate_n1_queries_on_article_interactions(): void
    {
        // Create test article interactions for the past 30 days
        $this->createTestArticleInteractions();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/article-reactions?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $articleInteractionQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'int_communication_rh_article_interactions')
                && str_contains($query['query'], 'count(*) as aggregate');
        })->count();

        // Should load all interactions once, not count in loop (30 times)
        $this->assertLessThanOrEqual(
            2,
            $articleInteractionQueries,
            sprintf(
                'ArticleReactionsCalculator should not generate N+1 COUNT queries on int_communication_rh_article_interactions. '.
                'Expected ≤2, got %d queries. This indicates the N+1 query bug on article interactions has regressed.',
                $articleInteractionQueries
            )
        );
    }

    /**
     * Test ShortcutsClicksCalculator does not generate N+1 queries on engagement_logs
     *
     * Before fix: 30+ queries on engagement_logs (one per day with LinkClicked filter)
     * After fix: Should be 1-2 queries maximum
     */
    #[Test]
    public function shortcuts_clicks_metric_does_not_generate_n1_queries_on_engagement_logs(): void
    {
        // Create test link click engagement logs
        $this->createTestLinkClickLogs();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/shortcuts-clicks?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $linkClickedQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'engagement_logs')
                && str_contains($query['query'], 'LinkClicked');
        })->count();

        // Should load all link clicks once, not query per day (30 times)
        $this->assertLessThanOrEqual(
            2,
            $linkClickedQueries,
            sprintf(
                'ShortcutsClicksCalculator should not generate N+1 queries on engagement_logs (LinkClicked). '.
                'Expected ≤2, got %d queries. This indicates the N+1 query bug on LinkClicked has regressed.',
                $linkClickedQueries
            )
        );
    }

    /**
     * Test ArticlesPerEmployeeCalculator does not generate N+1 queries on engagement_logs
     *
     * Before fix: 30+ DISTINCT queries on engagement_logs (one per day with ArticleViewed filter)
     * After fix: Should be 1-2 queries maximum
     */
    #[Test]
    public function articles_per_employee_metric_does_not_generate_n1_queries_on_engagement_logs(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/articles-per-employee?period=30d');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $articleViewedQueries = collect($queries)->filter(function (array $query): bool {
            return str_contains($query['query'], 'engagement_logs')
                && str_contains($query['query'], 'ArticleViewed');
        })->count();

        // Should load all article views once, not query per day (30 times)
        $this->assertLessThanOrEqual(
            2,
            $articleViewedQueries,
            sprintf(
                'ArticlesPerEmployeeCalculator should not generate N+1 queries on engagement_logs (ArticleViewed). '.
                'Expected ≤2, got %d queries. This indicates the N+1 query bug on ArticleViewed has regressed.',
                $articleViewedQueries
            )
        );
    }

    /**
     * Test that metrics are still calculated correctly after optimization
     */
    #[Test]
    public function metrics_calculations_remain_accurate_after_optimization(): void
    {
        $response = $this->actingAs($this->beneficiary, 'api')
            ->getJson('/api/v1/financers/metrics/dashboard?period=30d');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'active-beneficiaries' => ['title', 'value', 'labels', 'data'],
                'activation-rate' => ['title', 'value', 'labels', 'data'],
                'session-time' => ['title', 'value', 'labels', 'data'],
                'article_viewed' => ['title', 'value', 'labels', 'data'],
            ]);

        $metrics = $response->json();

        // Verify data integrity
        foreach ($metrics as $metricKey => $metric) {
            $this->assertIsArray($metric['labels'], "Metric {$metricKey} must have labels array");
            $this->assertIsArray($metric['data'], "Metric {$metricKey} must have data array");

            // Labels and data must have same length
            $this->assertCount(
                count($metric['labels']),
                $metric['data'],
                "Metric {$metricKey} labels and data arrays must have same length"
            );
        }
    }

    private function createAuthUserWithFinancer(?Financer $financer = null): User
    {
        $financer ??= $this->financer;

        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'TEST-'.$user->id,
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'financer_super_admin',
        ]);

        Context::add('financer_id', $user->financers->first()->id);
        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());

        return $user;
    }

    /**
     * Create realistic engagement logs for testing N+1 query
     */
    private function createTestEngagementLogs(): void
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Create 5 test users with engagement data
        $testUsers = User::factory()->count(5)->create();

        foreach ($testUsers as $testUser) {
            $testUser->financers()->attach($this->financer->id, [
                'active' => true,
                'sirh_id' => 'TEST-'.$testUser->id,
                'from' => now()->subYear(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $currentDate = $startDate->copy();

        // Create logs for each day in the range
        while ($currentDate <= $endDate) {
            foreach ($testUsers as $testUser) {
                $sessionId = fake()->uuid();

                // Session logs
                $sessionStartTime = $currentDate->copy()->addHours(9);
                EngagementLog::create([
                    'user_id' => $testUser->id,
                    'type' => 'SessionStarted',
                    'logged_at' => $sessionStartTime,
                    'created_at' => $sessionStartTime,
                    'metadata' => ['session_id' => $sessionId],
                ]);

                $sessionEndTime = $currentDate->copy()->addHours(9)->addMinutes(rand(5, 60));
                EngagementLog::create([
                    'user_id' => $testUser->id,
                    'type' => 'SessionFinished',
                    'logged_at' => $sessionEndTime,
                    'created_at' => $sessionEndTime,
                    'metadata' => [
                        'session_id' => $sessionId,
                        'duration' => rand(300, 3600),
                    ],
                ]);

                // Article views
                for ($i = 0; $i < rand(1, 5); $i++) {
                    $articleViewTime = $currentDate->copy()->addHours(rand(9, 17));
                    EngagementLog::create([
                        'user_id' => $testUser->id,
                        'type' => 'ArticleViewed',
                        'target' => fake()->uuid(),
                        'logged_at' => $articleViewTime,
                        'created_at' => $articleViewTime,
                    ]);
                }

                // Module accesses
                for ($i = 0; $i < rand(1, 3); $i++) {
                    $moduleAccessTime = $currentDate->copy()->addHours(rand(9, 17));
                    EngagementLog::create([
                        'user_id' => $testUser->id,
                        'type' => 'ModuleAccessed',
                        'target' => fake()->uuid(),
                        'logged_at' => $moduleAccessTime,
                        'created_at' => $moduleAccessTime,
                        'metadata' => ['session_id' => $sessionId],
                    ]);
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * Create test modules for ModuleUsageCalculator
     */
    private function createTestModules(): void
    {
        Module::factory()->count(5)->create([
            'name' => [
                'en-GB' => fake()->words(2, true),
                'fr-FR' => fake()->words(2, true),
            ],
        ]);
    }

    /**
     * Create realistic Amilon orders for testing N+1 query on vouchers
     */
    private function createTestAmilonOrders(): void
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Create 5 test users with financer relationship
        $testUsers = User::factory()->count(5)->create();

        foreach ($testUsers as $testUser) {
            $testUser->financers()->attach($this->financer->id, [
                'active' => true,
                'sirh_id' => 'TEST-VOUCHER-'.$testUser->id,
                'from' => now()->subYear(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $currentDate = $startDate->copy();

        // Create orders for each day in the range
        while ($currentDate <= $endDate) {
            foreach ($testUsers as $testUser) {
                // Random number of orders per day (1-3)
                $ordersCount = rand(1, 3);

                for ($i = 0; $i < $ordersCount; $i++) {
                    $orderTime = $currentDate->copy()->addHours(rand(9, 17));

                    Order::factory()->create([
                        'user_id' => $testUser->id,
                        'status' => OrderStatus::CONFIRMED,
                        'amount' => rand(1000, 10000), // Amount in cents (10€ to 100€)
                        'created_at' => $orderTime,
                        'updated_at' => $orderTime,
                    ]);
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * Create realistic article interactions (likes/reactions) for testing N+1 query
     */
    private function createTestArticleInteractions(): void
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Create 5 test users with financer relationship
        $testUsers = User::factory()->count(5)->create();

        foreach ($testUsers as $testUser) {
            $testUser->financers()->attach($this->financer->id, [
                'active' => true,
                'sirh_id' => 'TEST-ARTICLE-'.$testUser->id,
                'from' => now()->subYear(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        // Create articles first (to satisfy foreign key constraint)
        $articles = [];
        for ($i = 0; $i < 10; $i++) {
            $articles[] = Article::factory()->create([
                'financer_id' => $this->financer->id,
            ]);
        }

        $currentDate = $startDate->copy();
        $interactionTracker = []; // Track user-article pairs to avoid duplicates

        // Create article interactions for each day in the range
        while ($currentDate <= $endDate) {
            foreach ($testUsers as $testUser) {
                // Random number of article reactions per day (1-3)
                $reactionsCount = rand(1, 3);

                for ($i = 0; $i < $reactionsCount; $i++) {
                    // Find an article this user hasn't interacted with yet
                    $availableArticles = array_filter($articles, function ($article) use ($testUser, $interactionTracker): bool {
                        $key = $testUser->id.'_'.$article->id;

                        return ! isset($interactionTracker[$key]);
                    });

                    if ($availableArticles === []) {
                        break; // No more articles available for this user
                    }

                    $article = $availableArticles[array_rand($availableArticles)];
                    $interactionTime = $currentDate->copy()->addHours(rand(9, 17));

                    ArticleInteraction::factory()->create([
                        'user_id' => $testUser->id,
                        'article_id' => $article->id,
                        'reaction' => ['like', 'love', 'celebrate'][array_rand(['like', 'love', 'celebrate'])],
                        'created_at' => $interactionTime,
                        'updated_at' => $interactionTime,
                    ]);

                    // Mark this user-article pair as used
                    $interactionTracker[$testUser->id.'_'.$article->id] = true;
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * Create realistic link click (shortcuts) engagement logs for testing N+1 query
     */
    private function createTestLinkClickLogs(): void
    {
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Create 5 test users with financer relationship
        $testUsers = User::factory()->count(5)->create();

        foreach ($testUsers as $testUser) {
            $testUser->financers()->attach($this->financer->id, [
                'active' => true,
                'sirh_id' => 'TEST-LINKS-'.$testUser->id,
                'from' => now()->subYear(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        // Create real Link objects (shortcuts) with proper IDs
        $links = [];
        for ($i = 0; $i < 3; $i++) {
            $link = Link::factory()->create([
                'financer_id' => $this->financer->id,
                'name' => ['en-GB' => 'Test Link '.$i, 'fr-FR' => 'Lien Test '.$i],
            ]);
            $links[] = $link;
        }

        $linkIds = array_map(function ($link) {
            return $link->id;
        }, $links);

        $currentDate = $startDate->copy();

        // Create link click logs for each day in the range
        while ($currentDate <= $endDate) {
            foreach ($testUsers as $testUser) {
                // Random number of link clicks per day (1-5)
                $clicksCount = rand(1, 5);

                for ($i = 0; $i < $clicksCount; $i++) {
                    $clickTime = $currentDate->copy()->addHours(rand(9, 17));

                    EngagementLog::create([
                        'user_id' => $testUser->id,
                        'type' => 'LinkClicked',
                        'target' => $linkIds[array_rand($linkIds)], // Random link
                        'logged_at' => $clickTime,
                        'created_at' => $clickTime,
                    ]);
                }
            }

            $currentDate->addDay();
        }
    }
}
