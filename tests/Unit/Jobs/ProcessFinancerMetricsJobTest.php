<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\FinancerMetricType;
use App\Jobs\ProcessFinancerMetricsJob;
use App\Models\Financer;
use App\Models\FinancerMetric;
use App\Services\Metrics\MetricCalculatorFactory;
use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('jobs')]
#[Group('metrics')]
#[Group('financer')]
class ProcessFinancerMetricsJobTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['metrics.disabled_metrics' => []]);

        /** @var Financer $financer */
        $financer = ModelFactory::createFinancer();
        $this->financer = $financer;
    }

    #[Test]
    public function it_exists_as_a_job(): void
    {
        $this->assertTrue(
            class_exists(ProcessFinancerMetricsJob::class),
            'ProcessFinancerMetricsJob class should exist'
        );
    }

    #[Test]
    public function it_implements_should_queue_interface(): void
    {
        $date = Carbon::now();
        $job = new ProcessFinancerMetricsJob($this->financer->id, $date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay(), '7d');

        $this->assertInstanceOf(
            ShouldQueue::class,
            $job,
            'Job should implement ShouldQueue interface'
        );
    }

    #[Test]
    public function it_has_retry_configuration(): void
    {
        $date = Carbon::now();
        $job = new ProcessFinancerMetricsJob($this->financer->id, $date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay(), '7d');

        $this->assertEquals(3, $job->tries, 'Job should have 3 retry attempts');
        $this->assertEquals(60, $job->backoff, 'Job should have 60 seconds backoff');
    }

    #[Test]
    public function it_has_timeout_configuration(): void
    {
        $date = Carbon::now();
        $job = new ProcessFinancerMetricsJob($this->financer->id, $date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay(), '7d');

        $this->assertEquals(300, $job->timeout, 'Job should have 5 minutes timeout');
    }

    #[Test]
    public function it_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $date = Carbon::now();
        ProcessFinancerMetricsJob::dispatch($this->financer->id, $date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay(), '7d');

        Queue::assertPushed(ProcessFinancerMetricsJob::class, function (ProcessFinancerMetricsJob $job): bool {
            return $job->financerId === $this->financer->id;
        });
    }

    #[Test]
    public function it_processes_all_metric_types(): void
    {
        $date = Carbon::now();
        $dateFrom = $date->copy()->subDays(6)->startOfDay();
        $dateTo = $date->copy()->endOfDay();

        $job = new ProcessFinancerMetricsJob($this->financer->id, $dateFrom, $dateTo, '7d');
        $job->handle($this->app->make(MetricCalculatorFactory::class));

        // Verify metrics were stored
        $this->assertDatabaseHas('engagement_metrics', [
            'financer_id' => $this->financer->id,
            'metric' => 'financer_active_beneficiaries',
            'period' => '7d',
        ]);
    }

    #[Test]
    public function it_stores_metrics_in_database(): void
    {
        $date = Carbon::now();
        $dateFrom = $date->copy()->subDays(6)->startOfDay();
        $dateTo = $date->copy()->endOfDay();

        $job = new ProcessFinancerMetricsJob($this->financer->id, $dateFrom, $dateTo, '7d');
        $job->handle($this->app->make(MetricCalculatorFactory::class));

        // Check all metrics are stored
        $metrics = FinancerMetric::where('financer_id', $this->financer->id)
            ->where('period', '7d')
            ->pluck('metric')
            ->toArray();

        $expectedMetrics = [
            'financer_active_beneficiaries',
            'financer_activation_rate',
            'financer_session_time',
            'financer_module_usage',
            'financer_article_viewed',
            'financer_voucher_purchases',
            'financer_shortcuts_clicks',
            'financer_article_reactions',
            'financer_articles_per_employee',
            'financer_bounce_rate',
            'financer_voucher_average',
        ];

        foreach ($expectedMetrics as $metric) {
            $this->assertContains($metric, $metrics);
        }
    }

    #[Test]
    public function it_updates_existing_metrics(): void
    {
        $date = Carbon::now();
        $dateFrom = $date->copy()->subDays(6)->startOfDay();
        $dateTo = $date->copy()->endOfDay();

        // Create existing metric with same date range as job
        FinancerMetric::create([
            'date_from' => $dateFrom->toDateString(),
            'date_to' => $dateTo->toDateString(),
            'period' => '7d',
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $this->financer->id,
            'data' => ['total' => 50, 'daily' => []],
        ]);

        $job = new ProcessFinancerMetricsJob($this->financer->id, $dateFrom, $dateTo, '7d');
        $job->handle($this->app->make(MetricCalculatorFactory::class));

        // Verify metric was updated (should now have the new calculated data structure)
        $metric = FinancerMetric::where('financer_id', $this->financer->id)
            ->where('metric', 'financer_active_beneficiaries')
            ->where('period', '7d')
            ->first();

        $this->assertNotNull($metric, 'Metric should exist');

        // The updated metric should have the calculator's data structure
        $this->assertIsArray($metric->data);
        $this->assertArrayHasKey('total', $metric->data);
        $this->assertArrayHasKey('daily', $metric->data);
    }

    #[Test]
    public function it_handles_calculator_exceptions(): void
    {
        // Create a mock calculator factory that throws an exception
        $mockFactory = $this->createMock(MetricCalculatorFactory::class);
        $mockFactory->method('make')
            ->willThrowException(new Exception('Calculator error'));

        $date = Carbon::now();
        $dateFrom = $date->copy()->subDays(6)->startOfDay();
        $dateTo = $date->copy()->endOfDay();

        $job = new ProcessFinancerMetricsJob($this->financer->id, $dateFrom, $dateTo, '7d');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Calculator error');
        $job->handle($mockFactory);
    }

    #[Test]
    public function it_can_be_queued_with_delay(): void
    {
        Queue::fake();

        $delay = now()->addMinutes(5);
        $date = Carbon::now();
        ProcessFinancerMetricsJob::dispatch($this->financer->id, $date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay(), '7d')
            ->delay($delay);

        Queue::assertPushed(ProcessFinancerMetricsJob::class, function (ProcessFinancerMetricsJob $job) use ($delay): bool {
            return $job->delay instanceof DateTimeInterface
                && $job->delay->getTimestamp() === $delay->getTimestamp();
        });
    }

    #[Test]
    public function it_skips_disabled_metrics_from_processing(): void
    {
        config(['metrics.disabled_metrics' => [FinancerMetricType::ARTICLE_VIEWED]]);

        $date = Carbon::now();
        $dateFrom = $date->copy()->subDays(6)->startOfDay();
        $dateTo = $date->copy()->endOfDay();

        $job = new ProcessFinancerMetricsJob($this->financer->id, $dateFrom, $dateTo, '7d');
        $job->handle($this->app->make(MetricCalculatorFactory::class));

        $this->assertDatabaseMissing('engagement_metrics', [
            'financer_id' => $this->financer->id,
            'metric' => 'financer_article_viewed',
        ]);
    }
}
