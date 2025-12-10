<?php

namespace App\Console\Commands\Metrics;

use App\Models\EngagementMetric;
use App\Services\EngagementMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CalculateEngagementMetricsCommand extends Command
{
    protected $signature = 'metrics:calculate-daily
                            {--from= : Start date in Y-m-d}
                            {--to= : End date in Y-m-d}';

    protected $description = 'Calculates and stores engagement metrics for a given period (default: last 7 days)';

    public function handle(): void
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $this->info("Calculation of engagement metrics from {$from->toDateString()} to {$to->toDateString()}...");

        $service = new EngagementMetricsService;
        $metrics = $service->calculateRangeMetrics($from, $to);

        foreach ($metrics as $type => $entries) {
            foreach ($entries as $target => $value) {
                EngagementMetric::updateOrCreate([
                    'date' => $to->toDateString(), // ou la date de fin comme repère
                    'metric' => $type,
                    'module' => $target,
                ], [
                    'id' => Str::uuid(),
                    'data' => [
                        'value' => $value,
                        'from' => $from->toDateString(),
                        'to' => $to->toDateString(),
                    ],
                ]);
            }
        }

        $this->info('✔️ Metrics stored in engagement_metrics.');
    }
}
