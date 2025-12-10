<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\FinancerMetricType;
use App\Models\FinancerMetric;
use App\Services\Metrics\MetricCalculatorFactory;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFinancerMetricsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying.
     */
    public int $backoff = 60;

    /**
     * The maximum number of seconds the job should run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly string $financerId,
        public readonly Carbon $dateFrom,
        public readonly Carbon $dateTo,
        public readonly string $period
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MetricCalculatorFactory $calculatorFactory): void
    {
        $startDate = $this->dateFrom->copy()->startOfDay();
        $endDate = $this->dateTo->copy()->endOfDay();

        Log::info('Processing financer metrics', [
            'financer_id' => $this->financerId,
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'period' => $this->period,
        ]);

        // Process each metric type from the enum
        $metricTypes = FinancerMetricType::activeValues();

        foreach ($metricTypes as $metricTypeValue) {
            $metricType = FinancerMetricType::fromValue($metricTypeValue);

            try {
                // Get calculator for this metric type
                $calculator = $calculatorFactory->make($metricTypeValue);

                // Calculate metric data using the calculator
                $data = $calculator->calculate($this->financerId, $startDate, $endDate, $this->period);

                // Store or update metric
                FinancerMetric::updateOrCreate(
                    [
                        'date_from' => $this->dateFrom->toDateString(),
                        'date_to' => $this->dateTo->toDateString(),
                        'metric' => $metricType->getMetricName(),
                        'financer_id' => $this->financerId,
                        'period' => $this->period,
                    ],
                    [
                        'data' => $data,
                    ]
                );

                Log::info('Metric stored successfully', [
                    'financer_id' => $this->financerId,
                    'metric_type' => $metricType->getMetricName(),
                    'period' => $this->period,
                    'data_keys' => array_keys($data),
                ]);
            } catch (Exception $e) {
                Log::error('Failed to process metric', [
                    'financer_id' => $this->financerId,
                    'metric_type' => $metricType->getMetricName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Re-throw to trigger job retry
                throw $e;
            }
        }

        Log::info('Completed processing financer metrics', [
            'financer_id' => $this->financerId,
            'date_from' => $this->dateFrom->toDateString(),
            'date_to' => $this->dateTo->toDateString(),
            'period' => $this->period,
        ]);
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return "financer-metrics-{$this->financerId}-{$this->period}-{$this->dateFrom->toDateString()}-{$this->dateTo->toDateString()}";
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['financer-metrics', "financer:{$this->financerId}", "period:{$this->period}"];
    }
}
