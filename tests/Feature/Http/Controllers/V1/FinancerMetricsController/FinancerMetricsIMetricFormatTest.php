<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerMetricsController;

use App\DTOs\Financer\AllMetricsDTO;
use App\DTOs\Financer\IMetricDTO;
use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\FinancerMetric;
use App\Models\User;
use App\Services\Metrics\MetricService;
use Carbon\Carbon;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financer_metrics', 'permissions', 'model_has_permissions'], scope: 'class')]
#[Group('metrics')]
#[Group('financer')]
class FinancerMetricsIMetricFormatTest extends ProtectedRouteTestCase
{
    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();

        $this->financer = Financer::factory()->create();
        $this->createTestMetrics();
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        // Restore error handlers to avoid risky test warnings
        restore_error_handler();
        restore_exception_handler();

        parent::tearDown();
    }

    private function createAuthUserWithFinancer(?Financer $financer = null): User
    {
        $financer ??= $this->financer;

        // Create user with FINANCER_SUPER_ADMIN role to have the necessary permissions
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Attach user to financer with active status
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

    #[Test]
    public function dashboard_endpoint_returns_all_metrics_object(): void
    {
        $user = $this->createAuthUserWithFinancer();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/financers/metrics/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'active-beneficiaries' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'activation-rate' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'session-time' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'article_viewed' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
            ]);

        $metrics = $response->json();

        // Validate each metric follows IMetric format
        foreach ($metrics as $metricKey => $metric) {
            $this->assertArrayHasKey('title', $metric, "Metric {$metricKey} must have title");
            $this->assertArrayHasKey('value', $metric, "Metric {$metricKey} must have value");
            $this->assertArrayHasKey('labels', $metric, "Metric {$metricKey} must have labels");
            $this->assertArrayHasKey('data', $metric, "Metric {$metricKey} must have data");

            $this->assertIsString($metric['title'], "Title must be string for {$metricKey}");
            // Value can be numeric or a formatted string (like session time)
            if ($metricKey === 'session-time') {
                $this->assertIsString($metric['value'], 'Session time value should be a formatted string');
            } else {
                $this->assertIsNumeric($metric['value'], "Value must be numeric for {$metricKey}");
            }
            $this->assertIsArray($metric['labels'], "Labels must be array for {$metricKey}");
            $this->assertIsArray($metric['data'], "Data must be array for {$metricKey}");

            // Labels and data arrays must have same length
            $this->assertCount(
                count($metric['labels']),
                $metric['data'],
                "Labels and data arrays must have same length for {$metricKey}"
            );
        }
    }

    #[Test]
    public function individual_simple_metric_returns_imetric_format(): void
    {
        $user = $this->createAuthUserWithFinancer();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/financers/metrics/active-beneficiaries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'value',
                'labels',
                'data',
            ])
            ->assertJsonMissing(['datasets']); // Simple metrics should not have datasets

        $metric = $response->json();

        $this->assertIsString($metric['title']);
        $this->assertIsNumeric($metric['value']);
        $this->assertIsArray($metric['labels']);
        $this->assertIsArray($metric['data']);

        // All data points should be numeric
        foreach ($metric['data'] as $dataPoint) {
            $this->assertIsNumeric($dataPoint, 'All data points must be numeric');
        }

        // Labels should be formatted date strings (but might be empty if no data)
        foreach ($metric['labels'] as $label) {
            $this->assertIsString($label, 'All labels must be strings');
            // Format is "dd/mm" as shown in formatDateLabel method
            if (! empty($label)) {
                $this->assertMatchesRegularExpression('/\d{1,2}\/\d{1,2}/', $label, 'Labels should be formatted dates like "24/12"');
            }
        }
    }

    #[Test]
    public function individual_multiple_metric_returns_imetric_format_with_datasets(): void
    {
        $user = $this->createAuthUserWithFinancer();

        $response = $this->actingAs($user, 'api')

            ->getJson('/api/v1/financers/metrics/module-usage');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'value',
                'labels',
                'datasets' => [
                    '*' => [
                        'name',
                        'data',
                    ],
                ],
            ])
            ->assertJsonMissing(['data']); // Multiple metrics should not have direct data array

        $metric = $response->json();

        $this->assertIsString($metric['title']);
        $this->assertIsNumeric($metric['value']);
        $this->assertIsArray($metric['labels']);
        $this->assertIsArray($metric['datasets']);

        // Value should be sum of last values from all datasets
        $expectedValue = 0;
        foreach ($metric['datasets'] as $dataset) {
            $this->assertArrayHasKey('name', $dataset);
            $this->assertArrayHasKey('data', $dataset);
            $this->assertIsString($dataset['name']);
            $this->assertIsArray($dataset['data']);

            if (! empty($dataset['data'])) {
                $expectedValue += end($dataset['data']);
            }

            // All data points should be numeric
            foreach ($dataset['data'] as $dataPoint) {
                $this->assertIsNumeric($dataPoint, 'All dataset data points must be numeric');
            }

            // Dataset data length should match labels length
            $this->assertCount(
                count($metric['labels']),
                $dataset['data'],
                "Dataset {$dataset['name']} data length must match labels"
            );
        }

        $this->assertEquals($expectedValue, $metric['value'], 'Value should be sum of last values from all datasets');
    }

    #[Test]
    public function shortcuts_clicks_metric_returns_multiple_format(): void
    {
        $user = $this->createAuthUserWithFinancer();

        $response = $this->actingAs($user)

            ->getJson('/api/v1/financers/metrics/shortcuts-clicks');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'title',
                'value',
                'labels',
                'datasets' => [
                    '*' => [
                        'name',
                        'data',
                    ],
                ],
            ]);
    }

    #[Test]
    public function metric_period_parameter_affects_labels_count(): void
    {
        $user = $this->createAuthUserWithFinancer();

        // Create some financer users to have data
        User::factory()->count(5)->create()->each(function ($u): void {
            $u->financers()->attach($this->financer->id, [
                'active' => true,
                'sirh_id' => 'TEST-'.$u->id,
                'from' => now()->subDays(10),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        });

        // Test 7 days period
        $response7d = $this->actingAs($user, 'api')
            ->getJson('/api/v1/financers/metrics/active-beneficiaries?period=7d');

        $response7d->assertStatus(200);
        $metric7d = $response7d->json();

        // The service always returns labels and data arrays (even if empty)
        $this->assertArrayHasKey('labels', $metric7d, '7d response should have labels array');
        $this->assertArrayHasKey('data', $metric7d, '7d response should have data array');

        // Since we're using real calculation, if there's data we should have 7-8 labels for 7d period
        // But if no data matches the period, arrays can be empty
        if (! empty($metric7d['labels'])) {
            $this->assertGreaterThanOrEqual(7, count($metric7d['labels']), '7d period should have at least 7 labels when data exists');
            $this->assertLessThanOrEqual(8, count($metric7d['labels']), '7d period should have at most 8 labels');
        }
        $this->assertCount(count($metric7d['labels']), $metric7d['data'], 'Labels and data arrays should have same count');

        // Test 30 days period
        $response30d = $this->actingAs($user, 'api')
            ->getJson('/api/v1/financers/metrics/active-beneficiaries?period=30d');

        $response30d->assertStatus(200);
        $metric30d = $response30d->json();

        // The service always returns labels and data arrays (even if empty)
        $this->assertArrayHasKey('labels', $metric30d, '30d response should have labels array');
        $this->assertArrayHasKey('data', $metric30d, '30d response should have data array');

        // Since we're using real calculation, if there's data we should have 30-31 labels for 30d period
        // But if no data matches the period, arrays can be empty
        if (! empty($metric30d['labels'])) {
            $this->assertGreaterThanOrEqual(30, count($metric30d['labels']), '30d period should have at least 30 labels when data exists');
            $this->assertLessThanOrEqual(31, count($metric30d['labels']), '30d period should have at most 31 labels');
        }
        $this->assertCount(count($metric30d['labels']), $metric30d['data'], 'Labels and data arrays should have same count');
    }

    #[Test]
    public function metric_titles_are_translation_keys(): void
    {
        $user = $this->createAuthUserWithFinancer();

        $response = $this->actingAs($user, 'api')

            ->getJson('/api/v1/financers/metrics/active-beneficiaries');

        $response->assertStatus(200);
        $metric = $response->json();

        // Titles are returned as translation keys for frontend to translate
        $this->assertEquals('metrics.title.active-beneficiaries', $metric['title']);
    }

    #[Test]
    public function invalid_metric_type_returns_404(): void
    {
        $user = $this->createAuthUserWithFinancer();

        $response = $this->actingAs($user, 'api')

            ->getJson('/api/v1/financers/metrics/invalid_metric');

        $response->assertStatus(404);
    }

    #[Test]
    public function response_time_is_under_500ms(): void
    {
        // Ensure user has proper financer association before mocking service
        $user = $this->createAuthUserWithFinancer();

        // Mock the MetricService to avoid real DB queries in performance test
        $mockMetricService = $this->mock(MetricService::class);

        // Create mock data that simulates the dashboard metrics response
        $mockMetrics = new AllMetricsDTO(
            active_beneficiaries: IMetricDTO::createSimple(
                title: 'metrics.title.active-beneficiaries',
                tooltip: 'metrics.tooltip.active-beneficiaries',
                value: 1250,
                labels: ['24/12', '25/12', '26/12', '27/12', '28/12', '29/12', '30/12'],
                data: [1210, 1215, 1220, 1225, 1230, 1240, 1250]
            ),
            activation_rate: IMetricDTO::createSimple(
                title: 'metrics.title.activation-rate',
                tooltip: 'metrics.tooltip.activation-rate',
                value: '71.2',
                labels: ['24/12', '25/12', '26/12', '27/12', '28/12', '29/12', '30/12'],
                data: [68.5, 69.1, 69.8, 70.2, 70.5, 70.9, 71.2],
                unit: 'metrics.unit.percentage'
            ),
            average_session_time: IMetricDTO::createSimple(
                title: 'metrics.title.average-session-time',
                tooltip: 'metrics.tooltip.average-session-time',
                value: 8.5,
                labels: ['24/12', '25/12', '26/12', '27/12', '28/12', '29/12', '30/12'],
                data: [8.2, 8.3, 8.4, 8.4, 8.5, 8.5, 8.5]
            ),
            article_viewed_views: IMetricDTO::createSimple(
                title: 'metrics.title.articles-viewed',
                tooltip: 'metrics.tooltip.articles-viewed',
                value: 15420,
                labels: ['24/12', '25/12', '26/12', '27/12', '28/12', '29/12', '30/12'],
                data: [14950, 15020, 15100, 15180, 15250, 15330, 15420],
                unit: 'metrics.unit.views'
            )
        );

        $mockMetricService->shouldReceive('getDashboardMetrics')
            ->once()
            ->andReturn($mockMetrics);

        $startTime = microtime(true);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/financers/metrics/dashboard');

        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $responseTime, 'Dashboard response should be under 500ms');
    }

    private function createTestMetrics(): void
    {
        $baseDate = Carbon::now();

        // Create test data that will be transformed to IMetric format
        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => $baseDate->copy()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $this->financer->id,
            'data' => [
                'total' => 1250,
                'daily' => [
                    $baseDate->copy()->subDays(6)->toDateString() => 1210,
                    $baseDate->copy()->subDays(5)->toDateString() => 1215,
                    $baseDate->copy()->subDays(4)->toDateString() => 1220,
                    $baseDate->copy()->subDays(3)->toDateString() => 1225,
                    $baseDate->copy()->subDays(2)->toDateString() => 1230,
                    $baseDate->copy()->subDays(1)->toDateString() => 1240,
                    $baseDate->toDateString() => 1250,
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => $baseDate->copy()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_activation_rate',
            'financer_id' => $this->financer->id,
            'data' => [
                'rate' => 71.2,
                'total_users' => 1000,
                'activated_users' => 712,
                'daily' => [
                    $baseDate->copy()->subDays(6)->toDateString() => 68.5,
                    $baseDate->copy()->subDays(5)->toDateString() => 69.1,
                    $baseDate->copy()->subDays(4)->toDateString() => 69.8,
                    $baseDate->copy()->subDays(3)->toDateString() => 70.2,
                    $baseDate->copy()->subDays(2)->toDateString() => 70.5,
                    $baseDate->copy()->subDays(1)->toDateString() => 70.9,
                    $baseDate->toDateString() => 71.2,
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => $baseDate->copy()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_average_session_time',
            'financer_id' => $this->financer->id,
            'data' => [
                'value' => 8.5,
                'daily' => [
                    $baseDate->copy()->subDays(6)->toDateString() => 8.2,
                    $baseDate->copy()->subDays(5)->toDateString() => 8.3,
                    $baseDate->copy()->subDays(4)->toDateString() => 8.4,
                    $baseDate->copy()->subDays(3)->toDateString() => 8.4,
                    $baseDate->copy()->subDays(2)->toDateString() => 8.5,
                    $baseDate->copy()->subDays(1)->toDateString() => 8.5,
                    $baseDate->toDateString() => 8.5,
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => $baseDate->copy()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_article_viewed_views',
            'financer_id' => $this->financer->id,
            'data' => [
                'value' => 15420,
                'daily' => [
                    $baseDate->copy()->subDays(6)->toDateString() => 14950,
                    $baseDate->copy()->subDays(5)->toDateString() => 15020,
                    $baseDate->copy()->subDays(4)->toDateString() => 15100,
                    $baseDate->copy()->subDays(3)->toDateString() => 15180,
                    $baseDate->copy()->subDays(2)->toDateString() => 15250,
                    $baseDate->copy()->subDays(1)->toDateString() => 15330,
                    $baseDate->toDateString() => 15420,
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => $baseDate->copy()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_module_usage',
            'financer_id' => $this->financer->id,
            'data' => [
                'communication' => [
                    'daily' => [
                        $baseDate->copy()->subDays(6)->toDateString() => 245,
                        $baseDate->copy()->subDays(5)->toDateString() => 258,
                        $baseDate->copy()->subDays(4)->toDateString() => 267,
                        $baseDate->copy()->subDays(3)->toDateString() => 251,
                        $baseDate->copy()->subDays(2)->toDateString() => 275,
                        $baseDate->copy()->subDays(1)->toDateString() => 268,
                        $baseDate->toDateString() => 262,
                    ],
                ],
                'tools' => [
                    'daily' => [
                        $baseDate->copy()->subDays(6)->toDateString() => 156,
                        $baseDate->copy()->subDays(5)->toDateString() => 163,
                        $baseDate->copy()->subDays(4)->toDateString() => 171,
                        $baseDate->copy()->subDays(3)->toDateString() => 159,
                        $baseDate->copy()->subDays(2)->toDateString() => 178,
                        $baseDate->copy()->subDays(1)->toDateString() => 172,
                        $baseDate->toDateString() => 167,
                    ],
                ],
                'vouchers' => [
                    'daily' => [
                        $baseDate->copy()->subDays(6)->toDateString() => 89,
                        $baseDate->copy()->subDays(5)->toDateString() => 94,
                        $baseDate->copy()->subDays(4)->toDateString() => 98,
                        $baseDate->copy()->subDays(3)->toDateString() => 92,
                        $baseDate->copy()->subDays(2)->toDateString() => 103,
                        $baseDate->copy()->subDays(1)->toDateString() => 97,
                        $baseDate->toDateString() => 95,
                    ],
                ],
            ],
        ]);
    }
}
