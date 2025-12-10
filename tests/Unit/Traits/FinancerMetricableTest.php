<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\EngagementMetric;
use App\Models\Traits\FinancerMetricable;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('traits')]
#[Group('metrics')]
#[Group('financer')]
class FinancerMetricableTest extends TestCase
{
    use DatabaseTransactions;

    private EngagementMetric $testModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->testModel = new class extends EngagementMetric
        {
            use FinancerMetricable;

            protected $table = 'engagement_metrics';
        };
    }

    #[Test]
    public function it_uses_financer_metricable_trait(): void
    {
        $this->assertTrue(
            in_array(FinancerMetricable::class, class_uses($this->testModel)),
            'Model should use FinancerMetricable trait'
        );
    }

    #[Test]
    public function it_has_scope_by_financer(): void
    {
        $this->assertTrue(
            method_exists($this->testModel, 'scopeByFinancer'),
            'Trait should provide scopeByFinancer method'
        );
    }

    #[Test]
    public function it_filters_metrics_by_financer_id(): void
    {
        // Create test financer
        $financer = ModelFactory::createFinancer();

        // Create metrics with financer prefix
        EngagementMetric::create([
            'date_from' => now()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => now()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $financer->id,
            'data' => ['value' => 100],
        ]);

        // Create metric for different financer
        $otherFinancer = ModelFactory::createFinancer();
        EngagementMetric::create([
            'date_from' => now()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => now()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $otherFinancer->id,
            'data' => ['value' => 200],
        ]);

        // Create non-financer metric
        EngagementMetric::create([
            'date_from' => now()->subDays(6)->startOfDay()->toDateTimeString(),
            'date_to' => now()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'global_metric',
            'financer_id' => null,
            'data' => ['value' => 300],
        ]);

        // Test scope
        $results = $this->testModel->newQuery()
            ->byFinancer($financer->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($financer->id, $results->first()->financer_id);
        $this->assertStringStartsWith('financer_', $results->first()->metric);
    }

    #[Test]
    public function it_has_scope_by_date_range(): void
    {
        $this->assertTrue(
            method_exists($this->testModel, 'scopeByDateRange'),
            'Trait should provide scopeByDateRange method'
        );
    }

    #[Test]
    public function it_filters_metrics_by_date_range(): void
    {
        $financer = ModelFactory::createFinancer();

        // Create metrics for different dates
        EngagementMetric::create([
            'date_from' => now()->subDays(10)->startOfDay()->toDateTimeString(),
            'date_to' => now()->subDays(10)->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $financer->id,
            'data' => ['value' => 100],
        ]);

        EngagementMetric::create([
            'date_from' => now()->subDays(5)->startOfDay()->toDateTimeString(),
            'date_to' => now()->subDays(5)->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $financer->id,
            'data' => ['value' => 150],
        ]);

        EngagementMetric::create([
            'date_from' => now()->startOfDay()->toDateTimeString(),
            'date_to' => now()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $financer->id,
            'data' => ['value' => 200],
        ]);

        // Test date range scope
        $startDate = now()->subDays(7);
        $endDate = now();

        $results = $this->testModel->newQuery()
            ->byFinancer($financer->id)
            ->byDateRange($startDate, $endDate)
            ->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(function ($metric) use ($startDate, $endDate): bool {
            $metricDateFrom = Carbon::parse($metric->date_from);
            $metricDateTo = Carbon::parse($metric->date_to);

            return $metricDateFrom->greaterThanOrEqualTo($startDate->startOfDay()) &&
                   $metricDateTo->lessThanOrEqualTo($endDate->endOfDay());
        }));
    }

    #[Test]
    public function it_has_financer_relationship(): void
    {
        $this->assertTrue(
            method_exists($this->testModel, 'financer'),
            'Trait should provide financer relationship'
        );
    }

    #[Test]
    public function it_only_returns_financer_prefixed_metrics(): void
    {
        $financer = ModelFactory::createFinancer();

        // Create various metrics
        EngagementMetric::create([
            'date_from' => now()->startOfDay()->toDateTimeString(),
            'date_to' => now()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $financer->id,
            'data' => ['value' => 100],
        ]);

        EngagementMetric::create([
            'date_from' => now()->startOfDay()->toDateTimeString(),
            'date_to' => now()->endOfDay()->toDateTimeString(),
            'period' => '7d',
            'metric' => 'global_metric',
            'financer_id' => $financer->id,
            'data' => ['value' => 200],
        ]);

        $results = $this->testModel->newQuery()
            ->byFinancer($financer->id)
            ->get();

        $this->assertTrue($results->every(fn ($metric): bool => str_starts_with($metric->metric, 'financer_')
        ));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
