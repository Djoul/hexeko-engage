<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\EngagementLog;
use App\Models\FinancerUser;
use Carbon\Carbon;

class HrCommunicationsCalculator extends BaseMetricCalculator
{
    /**
     * Calculate HR communications metric (ArticleViewed)
     * Returns daily breakdown of unique article views per user
     * Each article is counted only once per user, even if viewed multiple times
     *
     * @return array{daily: array<int, array{date: string, count: int, unique_users: int}>, total: int, unique_users: int}
     */
    public function calculate(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): array {
        // Get users linked to this financer
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'daily' => [],
                'total' => 0,
                'unique_users' => 0,
            ];
        }

        // Load ALL article views in a single query (fixes N+1 query issue)
        $allArticleViews = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'ArticleViewed')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->select('user_id', 'target', 'created_at')
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at !== null ? $log->created_at->toDateString() : now()->toDateString();
            });

        // Initialize result structure
        $dailyData = [];
        $totalUniqueArticleViews = 0;
        $allUniqueUsers = [];
        $globalUniqueArticleUsers = []; // Track user-article pairs globally
        $currentDate = $dateFrom->copy()->startOfDay();

        // Process each day in memory (no more DB queries in loop)
        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();

            // Get unique article views for this day from pre-loaded data
            $dayArticleViews = $allArticleViews->get($dateKey) ?? collect();

            // Make unique by user_id + target
            $articleViews = $dayArticleViews->unique(function ($log): string {
                return $log->user_id.'_'.$log->target;
            });

            $dayUniqueArticleViews = $articleViews->count();
            $dayUniqueUsers = $articleViews->pluck('user_id')->unique()->count();

            // Track unique users across all days
            foreach ($articleViews as $view) {
                $allUniqueUsers[$view->user_id] = true;
                // Track global unique article-user pairs
                $globalKey = $view->user_id.'_'.$view->target;
                if (! array_key_exists($globalKey, $globalUniqueArticleUsers)) {
                    $globalUniqueArticleUsers[$globalKey] = true;
                    $totalUniqueArticleViews++;
                }
            }

            $dailyData[] = [
                'date' => $currentDate->toDateString(),
                'count' => $dayUniqueArticleViews,
                'unique_users' => $dayUniqueUsers,
            ];

            $currentDate->addDay();
        }

        return [
            'daily' => $dailyData,
            'total' => $totalUniqueArticleViews,
            'unique_users' => count($allUniqueUsers),
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::ARTICLE_VIEWED;
    }
}
