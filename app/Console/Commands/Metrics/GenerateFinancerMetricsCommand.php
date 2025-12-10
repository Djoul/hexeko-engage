<?php

declare(strict_types=1);

namespace App\Console\Commands\Metrics;

use App\Enums\MetricPeriod;
use App\Jobs\ProcessFinancerMetricsJob;
use App\Models\Financer;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class GenerateFinancerMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:generate-financer
                            {--financer= : The ID of a specific financer}
                            {--all : Generate metrics for all active financers}
                            {--period= : Metric period (7d, 30d, 3m, 6m, 12m, all, custom)}
                            {--date-from= : Start date for custom period (YYYY-MM-DD)}
                            {--date-to= : End date for custom period (YYYY-MM-DD)}
                            {--dry-run : Show what would be done without actually queuing jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate metrics for one or all financers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Validate options
        if (! $this->option('financer') && ! $this->option('all')) {
            $this->error('Please specify either --financer=ID or --all option.');

            return self::FAILURE;
        }

        if ($this->option('financer') && $this->option('all')) {
            $this->error('Cannot use both --financer and --all options together.');

            return self::FAILURE;
        }

        // Check if period is "all" - loop through all base periods
        if ($this->option('period') === 'all') {
            return $this->processAllPeriods();
        }

        // Process period and date options
        $periodData = $this->getPeriodData();
        if ($periodData === null) {
            return self::FAILURE;
        }

        // Process financers
        if ($this->option('all')) {
            return $this->processAllFinancers($periodData);
        }

        $financerId = $this->option('financer');
        if (! is_string($financerId) || $financerId === '') {
            $this->error('Financer ID is required when not using --all option');

            return self::FAILURE;
        }

        return $this->processSingleFinancer($financerId, $periodData);
    }

    /**
     * Get the period data based on command options.
     *
     * @return array{period: string, dateFrom: \Illuminate\Support\Carbon, dateTo: \Illuminate\Support\Carbon}|null
     */
    private function getPeriodData(): ?array
    {
        $period = $this->option('period') ?? MetricPeriod::getDefault();

        // Validate period (include 'all' in valid options)
        $validPeriods = array_merge(MetricPeriod::getValidPeriods(), ['all']);
        if (! in_array($period, $validPeriods)) {
            $this->error('Invalid period. Valid values are: '.implode(', ', $validPeriods));

            return null;
        }

        // Handle custom period
        if ($period === MetricPeriod::CUSTOM) {
            if (! $this->option('date-from') || ! $this->option('date-to')) {
                $this->error('Custom period requires both --date-from and --date-to options.');

                return null;
            }

            try {
                $dateFrom = Carbon::parse($this->option('date-from'))->startOfDay();
                $dateTo = Carbon::parse($this->option('date-to'))->endOfDay();
            } catch (Exception $e) {
                $this->error('Invalid date format. Please use YYYY-MM-DD.');

                return null;
            }

            if ($dateFrom->isAfter($dateTo)) {
                $this->error('Date from must be before or equal to date to.');

                return null;
            }

            return [
                'period' => $period,
                'dateFrom' => \Illuminate\Support\Carbon::parse($dateFrom->toDateTimeString()),
                'dateTo' => \Illuminate\Support\Carbon::parse($dateTo->toDateTimeString()),
            ];
        }

        // Handle predefined periods
        try {
            $dateRange = MetricPeriod::getDateRange($period);

            return [
                'period' => $period,
                'dateFrom' => \Illuminate\Support\Carbon::parse($dateRange['from']->toDateTimeString()),
                'dateTo' => \Illuminate\Support\Carbon::parse($dateRange['to']->toDateTimeString()),
            ];
        } catch (Exception $e) {
            $this->error('Failed to calculate date range: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Process a single financer.
     *
     * @param  array{period: string, dateFrom: \Illuminate\Support\Carbon, dateTo: \Illuminate\Support\Carbon}  $periodData
     */
    private function processSingleFinancer(string $financerId, array $periodData): int
    {
        $financer = Financer::find($financerId);

        if (! $financer) {
            $this->error("Financer not found: {$financerId}");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] Would queue metrics generation for financer: {$financerId}");
            $this->info("[DRY RUN] Period: {$periodData['period']} ({$periodData['dateFrom']->toDateString()} to {$periodData['dateTo']->toDateString()})");

            return self::SUCCESS;
        }

        $this->info("Queuing metrics generation for financer: {$financerId}");
        $this->info("Period: {$periodData['period']} ({$periodData['dateFrom']->toDateString()} to {$periodData['dateTo']->toDateString()})");

        ProcessFinancerMetricsJob::dispatch(
            $financerId,
            $periodData['dateFrom'],
            $periodData['dateTo'],
            $periodData['period']
        );

        $this->info('Metrics generation job queued successfully.');

        return self::SUCCESS;
    }

    /**
     * Process all active financers.
     */
    /**
     * @param  array{period: string, dateFrom: \Illuminate\Support\Carbon, dateTo: \Illuminate\Support\Carbon}  $periodData
     */
    private function processAllFinancers(array $periodData): int
    {
        $this->info('Queuing metrics generation for all active financers...');

        $financers = Financer::where('status', Financer::STATUS_ACTIVE)->get();

        if ($financers->isEmpty()) {
            $this->warn('No active financers found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[DRY RUN] Would queue metrics generation for {$financers->count()} financers");
            $this->info("[DRY RUN] Period: {$periodData['period']} ({$periodData['dateFrom']->toDateString()} to {$periodData['dateTo']->toDateString()})");

            return self::SUCCESS;
        }

        $this->info("Period: {$periodData['period']} ({$periodData['dateFrom']->toDateString()} to {$periodData['dateTo']->toDateString()})");

        $totalJobs = 0;

        if ($this->option('verbose')) {
            $this->withProgressBar($financers, function ($financer) use ($periodData, &$totalJobs): void {
                ProcessFinancerMetricsJob::dispatch(
                    $financer->id,
                    $periodData['dateFrom'],
                    $periodData['dateTo'],
                    $periodData['period']
                );
                $totalJobs++;
            });
            $this->newLine();
        } else {
            foreach ($financers as $financer) {
                ProcessFinancerMetricsJob::dispatch(
                    $financer->id,
                    $periodData['dateFrom'],
                    $periodData['dateTo'],
                    $periodData['period']
                );
                $totalJobs++;
            }
        }

        $this->info("Queued {$totalJobs} jobs for {$financers->count()} financers.");

        return self::SUCCESS;
    }

    /**
     * Process all base periods (7d, 30d, 3m, 6m, 12m).
     */
    private function processAllPeriods(): int
    {
        $basePeriods = [
            MetricPeriod::SEVEN_DAYS,
            MetricPeriod::THIRTY_DAYS,
            MetricPeriod::THREE_MONTHS,
            MetricPeriod::SIX_MONTHS,
            MetricPeriod::TWELVE_MONTHS,
        ];

        $this->info('Processing all base periods: '.implode(', ', $basePeriods));

        $totalJobsQueued = 0;

        foreach ($basePeriods as $period) {
            try {
                $dateRange = MetricPeriod::getDateRange($period);
                $periodData = [
                    'period' => $period,
                    'dateFrom' => \Illuminate\Support\Carbon::parse($dateRange['from']->toDateTimeString()),
                    'dateTo' => \Illuminate\Support\Carbon::parse($dateRange['to']->toDateTimeString()),
                ];

                $this->newLine();
                $this->info("Processing period: {$period} ({$periodData['dateFrom']->toDateString()} to {$periodData['dateTo']->toDateString()})");

                // Process financers for this period
                if ($this->option('all')) {
                    $result = $this->processAllFinancers($periodData);
                } else {
                    $financerId = $this->option('financer');
                    if (! is_string($financerId) || $financerId === '') {
                        $this->error('Financer ID is required when not using --all option');

                        return self::FAILURE;
                    }
                    $result = $this->processSingleFinancer($financerId, $periodData);
                }

                if ($result === self::FAILURE) {
                    $this->error("Failed to process period: {$period}");

                    return self::FAILURE;
                }

                // Count jobs (estimate based on financers count if --all)
                if ($this->option('all') && ! $this->option('dry-run')) {
                    $financersCount = Financer::where('status', Financer::STATUS_ACTIVE)->count();
                    $totalJobsQueued += $financersCount;
                }
            } catch (Exception $e) {
                $this->error("Error processing period {$period}: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->newLine();
        if ($this->option('dry-run')) {
            $this->info('[DRY RUN] Would have queued jobs for all '.count($basePeriods).' periods');
        } else {
            $this->info("Successfully queued {$totalJobsQueued} total jobs across all periods");
        }

        return self::SUCCESS;
    }
}
