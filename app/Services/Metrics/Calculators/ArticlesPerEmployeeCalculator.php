<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\EngagementLog;
use App\Models\FinancerUser;
use Carbon\Carbon;

class ArticlesPerEmployeeCalculator extends BaseMetricCalculator
{
    /**
     * Calculate articles per employee metric
     * Returns daily breakdown of average articles read per employee
     *
     * @return array{daily: array<int, array{date: string, value: float, active_employees: int, total_articles: int}>, total: float}
     */
    public function calculate(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): array {
        $financerUserIds = FinancerUser::where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUserIds->isEmpty()) {
            return [
                'daily' => [],
                'total' => 0,
            ];
        }

        // Initialize result structure
        $dailyData = [];
        $totalArticlesViewed = 0;
        $totalActiveEmployees = [];
        $currentDate = $dateFrom->copy()->startOfDay();

        // Load ALL article views in a single query (fixes N+1 query issue)
        $allArticleViews = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'ArticleViewed')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->select('user_id', 'target', 'created_at')
            ->get()
            ->groupBy(function (EngagementLog $log): string {
                return $log->created_at !== null ? $log->created_at->toDateString() : now()->toDateString();
            });

        // Process each day in memory (no more DB queries in loop)
        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();
            $dayViews = $allArticleViews->get($dateKey) ?? collect();

            // Group by user to count articles per user (unique target per user)
            $userArticleCount = $dayViews->groupBy('user_id')
                ->map(function ($userViews) {
                    return $userViews->unique('target')->count();
                });

            $dayActiveEmployees = $userArticleCount->count();
            $dayTotalArticles = (int) $userArticleCount->sum();
            $dayAverage = $dayActiveEmployees > 0 ? round((float) $dayTotalArticles / $dayActiveEmployees, 2) : 0.0;

            // Track total active employees
            foreach ($userArticleCount->keys() as $userId) {
                $totalActiveEmployees[$userId] = true;
            }

            $dailyData[] = [
                'date' => $dateKey,
                'value' => $dayAverage,
                'active_employees' => $dayActiveEmployees,
                'total_articles' => $dayTotalArticles,
            ];

            $totalArticlesViewed += $dayTotalArticles;
            $currentDate->addDay();
        }

        // Calculate overall average (average of daily averages)
        $sumOfAverages = 0;
        $daysWithData = 0;

        foreach ($dailyData as $day) {
            if ($day['active_employees'] > 0) {
                $sumOfAverages += $day['value'];
                $daysWithData++;
            }
        }

        $overallAverage = $daysWithData > 0
            ? round($sumOfAverages / $daysWithData, 2)
            : 0;

        return [
            'daily' => $dailyData,
            'total' => $overallAverage,
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::ARTICLES_PER_EMPLOYEE;
    }
}
