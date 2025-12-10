<?php

namespace App\Services\Metrics\Calculators;

use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use Carbon\Carbon;

abstract class BaseMetricCalculator implements MetricCalculatorInterface
{
    /**
     * Get cache key for this calculation
     */
    final public function getCacheKey(
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
    final public function getCacheTTL(): int
    {
        return 3600; // 1 hour by default
    }

    /**
     * Whether this calculator supports aggregation
     */
    final public function supportsAggregation(): bool
    {
        return true;
    }
}
