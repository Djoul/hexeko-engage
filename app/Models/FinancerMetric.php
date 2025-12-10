<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\FinancerMetricable;
use App\Models\Traits\HasDivisionThroughFinancer;
use App\Traits\GlobalCachable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FinancerMetric Model
 *
 * Extends EngagementMetric to provide financer-specific metrics functionality
 * with Redis Cluster caching support.
 *
 * @property string $id
 * @property string $date_from
 * @property string $date_to
 * @property string $metric
 * @property string|null $financer_id
 * @property string $period
 * @property array<string, mixed> $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Financer|null $financer
 *
 * @method static Builder<static> byFinancer(string $financerId)
 * @method static Builder<static> byDateRange(Carbon $startDate, Carbon $endDate)
 * @method static Builder<static> byMetricType(string $metricType)
 * @method static Builder<static> latest()
 * @method static static|null findCached(string $id, array<int, string> $with = [])
 * @method static Collection<int, static> allCached(array<int, string> $with = [])
 */
class FinancerMetric extends EngagementMetric
{
    use FinancerMetricable;
    use GlobalCachable;
    use HasDivisionThroughFinancer;

    /**
     * Cache TTL in seconds (1 hour)
     * Metrics are generated periodically, so 1 hour cache is appropriate
     */
    protected static int $cacheTtl = 3600;

    /**
     * Get the cache TTL for this model
     */
    public static function getCacheTtl(): int
    {
        return static::$cacheTtl;
    }
}
