<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Financer;
use App\Models\FinancerMetric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait FinancerMetricable
{
    /**
     * Scope to filter metrics by financer ID
     * Only returns metrics with 'financer_' prefix
     *
     * @param  Builder<FinancerMetric>  $query
     * @return Builder<FinancerMetric>
     */
    public function scopeByFinancer(Builder $query, string $financerId): Builder
    {
        return $query->where('financer_id', $financerId)
            ->where('metric', 'like', 'financer_%');
    }

    /**
     * Scope to filter metrics by date range
     *
     * @param  Builder<FinancerMetric>  $query
     * @return Builder<FinancerMetric>
     */
    public function scopeByDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereDate('date_from', '>=', $startDate->toDateString())
            ->whereDate('date_to', '<=', $endDate->toDateString());
    }

    /**
     * Get the financer associated with this metric.
     */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class, 'financer_id');
    }

    /**
     * Generate a cache key with financer context
     * Uses Redis Cluster safe key format with hash tags
     */
    public function getFinancerCacheKey(string $suffix = ''): string
    {
        $financerId = $this->financer_id ?? 'unknown';

        // Use hash tag for Redis Cluster to ensure all financer metrics are on same slot
        $baseKey = "{financer_metrics:$financerId}";

        $parts = [
            $baseKey,
            $this->metric ?? 'unknown',
            $this->date ?? 'unknown',
        ];

        if ($suffix !== '' && $suffix !== '0') {
            $parts[] = $suffix;
        }

        return implode(':', $parts);
    }

    /**
     * Get cache tag for financer metrics
     * Used for tag-based cache invalidation
     */
    public function getFinancerCacheTag(): string
    {
        $financerId = $this->financer_id ?? 'unknown';

        return "financer_metrics:{$financerId}";
    }

    /**
     * Scope to get metrics for a specific metric type
     *
     * @param  Builder<FinancerMetric>  $query
     * @return Builder<FinancerMetric>
     */
    public function scopeByMetricType(Builder $query, string $metricType): Builder
    {
        return $query->where('metric', "financer_$metricType");
    }

    /**
     * Scope to get latest metric for a financer
     *
     * @param  Builder<FinancerMetric>  $query
     * @return Builder<FinancerMetric>
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('date', 'desc')->limit(1);
    }
}
