<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\FinancerUser;
use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;

class ActiveBeneficiariesCalculator implements MetricCalculatorInterface
{
    /**
     * Calculate active beneficiaries metric
     *
     * @return array{total: int, daily: array<int, array{date: string, count: int}>}
     */
    public function calculate(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): array {
        // Load ALL financer users in a single query (fixes N+1 query issue)
        // We need all users, not just those created before $dateTo, to get accurate counts per day
        $allFinancerUsers = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->get();

        if ($allFinancerUsers->isEmpty()) {
            return [
                'total' => 0,
                'daily' => [],
            ];
        }

        // Get total count of active users created before or on the end date
        $totalActiveUsers = $allFinancerUsers->filter(function (FinancerUser $user) use ($dateTo): bool {
            return $user->created_at !== null && $user->created_at->lte($dateTo);
        })->count();

        // Get daily breakdown - count active users created up to each day IN MEMORY (no more DB queries)
        $dailyStats = [];

        // DatePeriod excludes the end date, so we need to use INCLUDE_END_DATE flag
        $period = new DatePeriod(
            $dateFrom,
            new DateInterval('P1D'),
            $dateTo,
            DatePeriod::INCLUDE_END_DATE
        );

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $carbonDate = Carbon::instance($date);

            // Count active users created before or on this date from pre-loaded collection
            $countForDate = $allFinancerUsers->filter(function (FinancerUser $user) use ($carbonDate): bool {
                return $user->created_at !== null && $user->created_at->lte($carbonDate->endOfDay());
            })->count();

            $dailyStats[] = [
                'date' => $dateStr,
                'count' => $countForDate,
            ];
        }

        return [
            'total' => $totalActiveUsers,
            'daily' => $dailyStats,
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::ACTIVE_BENEFICIARIES;
    }

    /**
     * Get cache key for this calculation
     */
    public function getCacheKey(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): string {
        return sprintf(
            'metrics:%s:%s:%s:%s:%s',
            $this->getMetricType(),
            $financerId,
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $period
        );
    }

    /**
     * Get cache TTL in seconds
     */
    public function getCacheTTL(): int
    {
        return 3600; // 1 hour
    }

    /**
     * Whether this calculator supports aggregation
     */
    public function supportsAggregation(): bool
    {
        return true;
    }
}
