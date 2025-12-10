<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FinancerMetricType;
use App\Models\EngagementLog;
use App\Models\FinancerMetric;
use App\Models\FinancerUser;
use App\Models\User;
use App\Support\RedisClusterHelper;
use Carbon\Carbon;
use DateInterval;
use DatePeriod;
use Illuminate\Support\Facades\Cache;

class FinancerMetricsService
{
    /**
     * Default cache TTL (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Database metric freshness threshold (24 hours)
     */
    private const DB_METRIC_FRESHNESS_HOURS = 24;

    /**
     * Redis cluster helper instance
     */
    private RedisClusterHelper $redisHelper;

    public function __construct()
    {
        $this->redisHelper = new RedisClusterHelper;
    }

    /**
     * Get active beneficiaries count for a financer within a date range
     *
     * @return array{total: int, daily: array<int, array{date: string, count: int}>}
     */
    public function getActiveBeneficiaries(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        /** @var array{total: int, daily: array<int, array{date: string, count: int}>} */
        $result = $this->getMetricWithLayers(
            $financerId,
            FinancerMetricType::ACTIVE_BENEFICIARIES,
            $startDate,
            $endDate,
            $period,
            function () use ($financerId, $startDate, $endDate): array {

                // Get active financer users created before the end date - SINGLE QUERY
                $activeFinancerUsers = FinancerUser::where('financer_id', $financerId)
                    ->where('active', true)
                    ->where('created_at', '<=', $endDate)
                    ->get();

                if ($activeFinancerUsers->isEmpty()) {
                    return [
                        'total' => 0,
                        'daily' => [],
                    ];
                }

                // Get total count of active users
                $totalActiveUsers = $activeFinancerUsers->count();

                // Get daily breakdown - count active users created up to each day
                // OPTIMIZED: Use in-memory collection filtering instead of DB queries
                $dailyStats = [];

                // DatePeriod excludes the end date, so we need to use INCLUDE_END_DATE flag
                $period = new DatePeriod(
                    $startDate,
                    new DateInterval('P1D'),
                    $endDate,
                    DatePeriod::INCLUDE_END_DATE
                );

                foreach ($period as $date) {
                    $dateStr = $date->format('Y-m-d');
                    $carbonDate = Carbon::instance($date)->endOfDay();

                    // Count from in-memory collection - NO DB QUERY
                    $countForDate = $activeFinancerUsers->filter(function (FinancerUser $user) use ($carbonDate): bool {
                        return $user->created_at <= $carbonDate;
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
        );

        return $result;
    }

    /**
     * Calculate activation rate for financer users
     *
     * @return array{rate: float, total_users: int, activated_users: int}
     */
    public function getActivationRate(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        /** @var array{rate: float, total_users: int, activated_users: int} */
        $result = $this->getMetricWithLayers(
            $financerId,
            FinancerMetricType::ACTIVATION_RATE,
            $startDate,
            $endDate,
            $period,
            function () use ($financerId, $endDate): array {
                // Total invités = tous les utilisateurs avec invitation_status='pending' pour ce financeur
                // We need to count users linked to this financer with pending invitations
                $totalInvited = User::where('invitation_status', 'pending')
                    ->where('created_at', '<=', $endDate)
                    ->whereHas('financers', function ($query) use ($financerId): void {
                        $query->where('financer_id', $financerId);
                    })
                    ->count();

                if ($totalInvited === 0) {
                    return [
                        'rate' => 0.0,
                        'total_users' => 0,
                        'activated_users' => 0,
                    ];
                }

                // Total inscrits = invités qui se sont inscrits (ont un user_id actif)
                $totalRegistered = FinancerUser::where('financer_id', $financerId)
                    ->where('created_at', '<=', $endDate)
                    ->whereActive(true)
                    ->count();
                // Calculer le taux d'activation : (inscrits / invités) × 100
                $rate = ($totalRegistered / $totalInvited) * 100;

                return [
                    'rate' => round($rate, 1),
                    'total_users' => $totalInvited, // Total des invités
                    'activated_users' => $totalRegistered, // Total des inscrits
                ];
            }
        );

        return $result;
    }

    /**
     * Calculate median session time for financer users
     *
     * @return array{median_minutes: int, total_sessions: int}
     */
    public function getMedianSessionTime(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        /** @var array{median_minutes: int, total_sessions: int} */
        $result = $this->getMetricWithLayers(
            $financerId,
            'median_session_time',
            $startDate,
            $endDate,
            $period,
            function () use ($financerId, $startDate, $endDate): array {
                // Get users linked to this financer
                $financerUserIds = FinancerUser::where('financer_id', $financerId)
                    ->where('active', true)
                    ->pluck('user_id');

                if ($financerUserIds->isEmpty()) {
                    return [
                        'median_minutes' => 0,
                        'total_sessions' => 0,
                    ];
                }

                // Get session durations
                $sessions = EngagementLog::whereIn('user_id', $financerUserIds)
                    ->where('type', 'SessionStarted')
                    ->whereBetween('logged_at', [$startDate, $endDate])
                    ->whereNotNull('metadata->duration')
                    ->pluck('metadata')
                    ->map(function ($metadata): int {
                        /** @var array<string, mixed> $metadataArray */
                        $metadataArray = is_array($metadata) ? $metadata : [];

                        return array_key_exists('duration', $metadataArray) && is_numeric($metadataArray['duration']) ? (int) $metadataArray['duration'] : 0;
                    })
                    ->filter(function ($duration): bool {
                        return $duration > 0;
                    })
                    ->sort()
                    ->values();

                if ($sessions->isEmpty()) {
                    return [
                        'median_minutes' => 0,
                        'total_sessions' => 0,
                    ];
                }

                // Calculate median
                $count = $sessions->count();
                $median = $count % 2 === 0
                    ? ((int) $sessions[$count / 2 - 1] + (int) $sessions[$count / 2]) / 2
                    : (int) $sessions[intval($count / 2)];

                return [
                    'median_minutes' => intval((float) $median / 60), // Convert seconds to minutes
                    'total_sessions' => $count,
                ];
            }
        );

        return $result;
    }

    /**
     * Get module usage statistics
     *
     * @return array<string, array{unique_users: int, total_uses: int}>
     */
    public function getModuleUsageStats(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        /** @var array<string, array{unique_users: int, total_uses: int}> */
        $result = $this->getMetricWithLayers(
            $financerId,
            'module_usage_stats',
            $startDate,
            $endDate,
            $period,
            function () use ($financerId, $startDate, $endDate): array {
                // Get users linked to this financer
                $financerUserIds = FinancerUser::where('financer_id', $financerId)
                    ->where('active', true)
                    ->pluck('user_id');

                if ($financerUserIds->isEmpty()) {
                    return [];
                }

                // Get module usage stats
                $moduleStats = EngagementLog::whereIn('user_id', $financerUserIds)
                    ->where('type', 'ModuleUsed')
                    ->whereBetween('logged_at', [$startDate, $endDate])
                    ->whereNotNull('target')
                    ->selectRaw('target as module, COUNT(DISTINCT user_id) as unique_users, COUNT(*) as total_uses')
                    ->groupBy('target')
                    ->get();

                $result = [];
                foreach ($moduleStats as $stat) {
                    $moduleAttr = $stat->getAttribute('module');
                    $module = is_scalar($moduleAttr) ? (string) $moduleAttr : '';
                    $uniqueUsers = $stat->getAttribute('unique_users');
                    $totalUses = $stat->getAttribute('total_uses');
                    $result[$module] = [
                        'unique_users' => is_numeric($uniqueUsers) ? (int) $uniqueUsers : 0,
                        'total_uses' => is_numeric($totalUses) ? (int) $totalUses : 0,
                    ];
                }

                return $result;
            }
        );

        return $result;
    }

    /**
     * Get HR communications view statistics
     *
     * @return array{articles: array{views: int, unique_users: int}, tools: array{clicks: int, unique_users: int}, total_interactions: int}
     */
    public function getHrCommunicationsViews(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        /** @var array{articles: array{views: int, unique_users: int}, tools: array{clicks: int, unique_users: int}, total_interactions: int} */
        $result = $this->getMetricWithLayers(
            $financerId,
            'article_viewed_views',
            $startDate,
            $endDate,
            $period,
            function () use ($financerId, $startDate, $endDate): array {
                // Get users linked to this financer
                $financerUserIds = FinancerUser::where('financer_id', $financerId)
                    ->where('active', true)
                    ->pluck('user_id');

                if ($financerUserIds->isEmpty()) {
                    return [
                        'articles' => ['views' => 0, 'unique_users' => 0],
                        'tools' => ['clicks' => 0, 'unique_users' => 0],
                        'total_interactions' => 0,
                    ];
                }

                // Get article views
                $articleStats = EngagementLog::whereIn('user_id', $financerUserIds)
                    ->where('type', 'ArticleViewed')
                    ->whereBetween('logged_at', [$startDate, $endDate])
                    ->selectRaw('COUNT(*) as views, COUNT(DISTINCT user_id) as unique_users')
                    ->first();

                // Get tool clicks
                $toolStats = EngagementLog::whereIn('user_id', $financerUserIds)
                    ->where('type', 'LinkClicked')
                    ->whereBetween('logged_at', [$startDate, $endDate])
                    ->selectRaw('COUNT(*) as clicks, COUNT(DISTINCT user_id) as unique_users')
                    ->first();

                return [
                    'articles' => [
                        'views' => $articleStats->views ?? 0,
                        'unique_users' => $articleStats->unique_users ?? 0,
                    ],
                    'tools' => [
                        'clicks' => $toolStats->clicks ?? 0,
                        'unique_users' => $toolStats->unique_users ?? 0,
                    ],
                    'total_interactions' => ($articleStats->views ?? 0) + ($toolStats->clicks ?? 0),
                ];
            }
        );

        return $result;
    }

    /**
     * Get cached metrics using Redis Cluster
     *
     * @return array<string, mixed>
     */
    public function getCachedMetrics(string $cacheKey, callable $callback, int $ttl = self::CACHE_TTL): array
    {
        // Use Redis Cluster safe key
        $safeKey = $this->redisHelper->key($cacheKey);

        $result = Cache::remember($safeKey, $ttl, function () use ($callback) {
            return $callback();
        });

        return is_array($result) ? $result : [];
    }

    /**
     * Invalidate cache for a specific financer
     */
    public function invalidateFinancerCache(string $financerId): void
    {
        $tag = "financer_metrics:{$financerId}";
        Cache::tags([$tag])->flush();
    }

    /**
     * Force recalculation of metrics by deleting stale DB entries
     * This will cause the next request to recalculate all metrics
     */
    public function forceRecalculateMetrics(string $financerId, ?Carbon $beforeDate = null): int
    {
        $query = FinancerMetric::where('module', $financerId)
            ->where('metric', 'like', 'financer_%');

        if ($beforeDate instanceof Carbon) {
            $query->where('created_at', '<', $beforeDate);
        }

        $deletedCount = $query->delete();

        // Also invalidate cache
        $this->invalidateFinancerCache($financerId);

        return is_numeric($deletedCount) ? (int) $deletedCount : 0;
    }

    /**
     * Get voucher purchases volume in euros
     *
     * @return array{total_volume: float, total_purchases: int, unique_users: int}
     */
    public function getVoucherPurchases(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        /** @var array{total_volume: float, total_purchases: int, unique_users: int} */
        $result = $this->getMetricWithLayers(
            $financerId,
            'voucher_purchases',
            $startDate,
            $endDate,
            $period,
            function () use ($financerId, $startDate, $endDate): array {
                $financerUserIds = FinancerUser::where('financer_id', $financerId)
                    ->where('active', true)
                    ->pluck('user_id');

                if ($financerUserIds->isEmpty()) {
                    return [
                        'total_volume' => 0,
                        'total_purchases' => 0,
                        'unique_users' => 0,
                    ];
                }

                $voucherStats = EngagementLog::whereIn('user_id', $financerUserIds)
                    ->where('type', 'VoucherPurchased')
                    ->whereBetween('logged_at', [$startDate, $endDate])
                    ->whereNotNull('metadata->amount')
                    ->selectRaw('SUM(CAST(metadata->>\'amount\' AS DECIMAL)) as total_volume, COUNT(*) as total_purchases, COUNT(DISTINCT user_id) as unique_users')
                    ->first();

                return [
                    'total_volume' => floatval($voucherStats->total_volume ?? 0),
                    'total_purchases' => $voucherStats->total_purchases ?? 0,
                    'unique_users' => $voucherStats->unique_users ?? 0,
                ];
            }
        );

        return $result;
    }

    /**
     * Get shortcuts clicks by type (multi-line)
     *
     * @return array<string, array{total_clicks: int, unique_users: int}>
     */
    public function getShortcutsClicks(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [];
        }

        $shortcutStats = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'ShortcutClicked')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->whereNotNull('target')
            ->selectRaw('target as shortcut_type, COUNT(*) as total_clicks, COUNT(DISTINCT user_id) as unique_users')
            ->groupBy('target')
            ->get();

        $result = [];
        foreach ($shortcutStats as $stat) {
            $shortcutTypeAttr = $stat->getAttribute('shortcut_type');
            $shortcutType = is_scalar($shortcutTypeAttr) ? (string) $shortcutTypeAttr : '';

            $totalClicksAttr = $stat->getAttribute('total_clicks');
            $uniqueUsersAttr = $stat->getAttribute('unique_users');

            $result[$shortcutType] = [
                'total_clicks' => is_numeric($totalClicksAttr) ? (int) $totalClicksAttr : 0,
                'unique_users' => is_numeric($uniqueUsersAttr) ? (int) $uniqueUsersAttr : 0,
            ];
        }

        return $result;
    }

    /**
     * Get article reactions (likes, shares, etc.)
     *
     * @return array{total_reactions: int, unique_users: int, unique_articles: int}
     */
    public function getArticleReactions(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'total_reactions' => 0,
                'unique_users' => 0,
                'unique_articles' => 0,
            ];
        }

        $reactionStats = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'ArticleReacted')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->selectRaw('COUNT(*) as total_reactions, COUNT(DISTINCT user_id) as unique_users, COUNT(DISTINCT target) as unique_articles')
            ->first();

        return [
            'total_reactions' => $reactionStats->total_reactions ?? 0,
            'unique_users' => $reactionStats->unique_users ?? 0,
            'unique_articles' => $reactionStats->unique_articles ?? 0,
        ];
    }

    /**
     * Get articles per active employee ratio
     *
     * @return array{articles_per_employee: float, total_articles_viewed: int, active_employees: int}
     */
    public function getArticlesPerEmployee(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'articles_per_employee' => 0,
                'total_articles_viewed' => 0,
                'active_employees' => 0,
            ];
        }

        // Get active employees (users with activity in the period)
        $activeEmployees = EngagementLog::whereIn('user_id', $financerUserIds)
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->distinct('user_id')
            ->count('user_id');

        // Get total articles viewed
        $totalArticlesViewed = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'ArticleViewed')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->count();

        $ratio = $activeEmployees > 0 ? $totalArticlesViewed / $activeEmployees : 0;

        return [
            'articles_per_employee' => round($ratio, 2),
            'total_articles_viewed' => $totalArticlesViewed,
            'active_employees' => $activeEmployees,
        ];
    }

    /**
     * Get bounce rate percentage (sessions without interaction)
     *
     * @return array{bounce_rate: float, total_sessions: int, bounce_sessions: int}
     */
    public function getBounceRate(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'bounce_rate' => 0,
                'total_sessions' => 0,
                'bounce_sessions' => 0,
            ];
        }

        // Get total sessions
        $totalSessions = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'SessionStarted')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->count();

        if ($totalSessions === 0) {
            return [
                'bounce_rate' => 0,
                'total_sessions' => 0,
                'bounce_sessions' => 0,
            ];
        }

        // Get bounce sessions (sessions with duration <= 30 seconds or no interactions)
        $bounceSessions = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'SessionStarted')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->where(function ($query): void {
                $query->whereNull('metadata->duration')
                    ->orWhere('metadata->duration', '<=', 30);
            })
            ->count();

        $bounceRate = ($bounceSessions / $totalSessions) * 100;

        return [
            'bounce_rate' => round($bounceRate, 1),
            'total_sessions' => $totalSessions,
            'bounce_sessions' => $bounceSessions,
        ];
    }

    /**
     * Get average voucher amount per purchase
     *
     * @return array{average_amount: float, total_purchases: int, total_volume: float}
     */
    public function getVoucherAverageAmount(string $financerId, Carbon $startDate, Carbon $endDate, string $period): array
    {
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'average_amount' => 0,
                'total_purchases' => 0,
                'total_volume' => 0,
            ];
        }

        $voucherStats = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'VoucherPurchased')
            ->whereBetween('logged_at', [$startDate, $endDate])
            ->whereNotNull('metadata->amount')
            ->selectRaw('AVG(CAST(metadata->>\'amount\' AS DECIMAL)) as average_amount, COUNT(*) as total_purchases, SUM(CAST(metadata->>\'amount\' AS DECIMAL)) as total_volume')
            ->first();

        return [
            'average_amount' => round(floatval($voucherStats->average_amount ?? 0), 2),
            'total_purchases' => $voucherStats->total_purchases ?? 0,
            'total_volume' => floatval($voucherStats->total_volume ?? 0),
        ];
    }

    /**
     * Generate cache key for a specific metric
     */
    private function generateCacheKey(string $financerId, string $metricType, Carbon $startDate, Carbon $endDate): string
    {
        return sprintf(
            '{financer_metrics:%s}:%s:%s:%s',
            $financerId,
            $metricType,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );
    }

    /**
     * Get or create metric from database
     * Checks if metric exists and is fresh, otherwise calculates and stores it
     *
     * @return array<string, mixed>
     */
    private function getOrCreateMetricFromDb(
        string $financerId,
        string $metricType,
        Carbon $startDate,
        Carbon $endDate,
        string $period,
        callable $calculator
    ): array {
        // For date ranges, we'll store as a single metric with the end date
        $endDate->toDateString();
        $fullMetricType = "financer_$metricType";

        // Check if metric exists in DB
        $existingMetric = FinancerMetric::byFinancer($financerId)
            ->where('metric', $fullMetricType)
            ->where('date_from', $startDate->startOfDay()->toDateTimeString())
            ->where('date_to', $endDate->endOfDay()->toDateTimeString())
            ->where('period', $period)
            ->first();

        // Check if metric is fresh (less than 24 hours old)
        if ($existingMetric && $existingMetric->created_at->diffInHours(now()) < self::DB_METRIC_FRESHNESS_HOURS) {
            // Check if the metric data includes the requested date range
            $metricData = $existingMetric->data;
            if (
                array_key_exists('start_date', $metricData) &&
                array_key_exists('end_date', $metricData) &&
                $metricData['start_date'] === $startDate->toDateString() &&
                $metricData['end_date'] === $endDate->toDateString()
            ) {
                return $metricData;
            }
        }

        // Calculate fresh metrics
        $calculatedData = $calculator();

        // Add date range info to the data
        $dataToStore = array_merge($calculatedData, [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        // Store in database
        FinancerMetric::updateOrCreate(
            [
                'financer_id' => $financerId,
                'metric' => $fullMetricType,
                'period' => $period,
                'date_from' => $startDate->startOfDay()->toDateTimeString(),
                'date_to' => $endDate->endOfDay()->toDateTimeString(),
            ],
            [
                'data' => $dataToStore,
            ]
        );

        return $calculatedData;
    }

    /**
     * Get metric with database and cache layers
     * This method combines DB persistence and Redis caching
     *
     * @return array<string, mixed>
     */
    private function getMetricWithLayers(
        string $financerId,
        string $metricType,
        Carbon $startDate,
        Carbon $endDate,
        string $period,
        callable $calculator
    ): array {
        $cacheKey = $this->generateCacheKey($financerId, $metricType, $startDate, $endDate);

        return $this->getCachedMetrics($cacheKey, function () use ($financerId, $metricType, $startDate, $endDate, $calculator, $period): array {
            return $this->getOrCreateMetricFromDb($financerId, $metricType, $startDate, $endDate, $period, $calculator);
        });
    }
}
