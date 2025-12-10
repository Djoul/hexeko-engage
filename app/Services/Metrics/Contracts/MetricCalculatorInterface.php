<?php

namespace App\Services\Metrics\Contracts;

use Carbon\Carbon;

interface MetricCalculatorInterface
{
    /**
     * Calculate the metric for the given parameters
     *
     * @return array<string, mixed>
     */
    public function calculate(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): array;

    /**
     * Get the metric type identifier
     */
    public function getMetricType(): string;

    /**
     * Get the cache key for this metric calculation
     */
    public function getCacheKey(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): string;

    /**
     * Get the cache TTL in seconds
     */
    public function getCacheTTL(): int;

    /**
     * Whether this calculator supports period aggregation
     */
    public function supportsAggregation(): bool;
}
