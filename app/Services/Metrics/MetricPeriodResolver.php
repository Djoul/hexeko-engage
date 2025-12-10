<?php

namespace App\Services\Metrics;

use App\Enums\MetricPeriod;
use App\Exceptions\InvalidPeriodException;
use Carbon\Carbon;
use InvalidArgumentException;

class MetricPeriodResolver
{
    /**
     * Resolve period to date range
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolve(
        string $period,
        ?Carbon $customFrom = null,
        ?Carbon $customTo = null,
        ?Carbon $referenceDate = null
    ): array {
        if ($period === MetricPeriod::CUSTOM) {
            if (! $customFrom instanceof Carbon || ! $customTo instanceof Carbon) {
                throw new InvalidArgumentException('Custom period requires date_from and date_to');
            }

            if ($customFrom->isAfter($customTo)) {
                throw new InvalidArgumentException('date_from must be before date_to');
            }

            return [
                'from' => $customFrom,
                'to' => $customTo,
            ];
        }

        $reference = $referenceDate ?? Carbon::now();

        return match ($period) {
            MetricPeriod::SEVEN_DAYS => [
                'from' => $reference->copy()->subDays(6)->startOfDay(),
                'to' => $reference->copy()->endOfDay(),
            ],
            MetricPeriod::THIRTY_DAYS => [
                'from' => $reference->copy()->subDays(29)->startOfDay(),
                'to' => $reference->copy()->endOfDay(),
            ],
            MetricPeriod::THREE_MONTHS => [
                'from' => $reference->copy()->subMonths(3)->addDay()->startOfDay(),
                'to' => $reference->copy()->endOfDay(),
            ],
            MetricPeriod::SIX_MONTHS => [
                'from' => $reference->copy()->subMonths(6)->endOfMonth()->startOfDay(),
                'to' => $reference->copy()->endOfDay(),
            ],
            MetricPeriod::TWELVE_MONTHS => [
                'from' => $reference->copy()->subYear()->endOfMonth()->startOfDay(),
                'to' => $reference->copy()->endOfDay(),
            ],
            default => throw new InvalidPeriodException("Invalid period: {$period}"),
        };
    }

    /**
     * Validate if period is valid
     *
     * @throws InvalidPeriodException
     */
    public function validatePeriod(string $period): void
    {
        $validPeriods = MetricPeriod::getValidPeriods();

        if (! in_array($period, $validPeriods, true)) {
            throw new InvalidPeriodException("Invalid period: {$period}");
        }
    }

    /**
     * Get cache TTL for a given period
     *
     * @return int TTL in seconds
     */
    public function getCacheTtlForPeriod(string $period): int
    {
        return match ($period) {
            MetricPeriod::SEVEN_DAYS => 3600,      // 1 hour
            MetricPeriod::THIRTY_DAYS => 7200,     // 2 hours
            MetricPeriod::THREE_MONTHS => 14400,   // 4 hours
            MetricPeriod::SIX_MONTHS => 28800,     // 8 hours
            MetricPeriod::TWELVE_MONTHS => 86400,  // 24 hours
            MetricPeriod::CUSTOM => 3600,          // 1 hour
            default => 3600
        };
    }
}
