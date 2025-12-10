<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Models\FinancerUser;
use Carbon\Carbon;

class ArticleReactionsCalculator extends BaseMetricCalculator
{
    /**
     * Calculate article reactions metric (likes)
     * Returns daily breakdown of article likes
     *
     * @return array{daily: array<int, array{date: string, count: int}>, total: int}
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

        // Load ALL article interactions in a single query (fixes N+1 query issue)
        $allInteractions = ArticleInteraction::whereIn('user_id', $financerUserIds)
            ->whereNotNull('reaction')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->select('user_id', 'created_at')
            ->get()
            ->groupBy(function (ArticleInteraction $interaction): string {
                return $interaction->created_at !== null ? $interaction->created_at->toDateString() : now()->toDateString();
            });

        // Process day by day in memory (no more DB queries in loop)
        $dailyData = [];
        $totalLikes = 0;
        $currentDate = $dateFrom->copy()->startOfDay();

        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();

            // Get interactions for this day from pre-loaded data
            $dayInteractions = $allInteractions->get($dateKey) ?? collect();
            $dayLikes = $dayInteractions->count();

            $dailyData[] = [
                'date' => $dateKey,
                'count' => $dayLikes,
            ];

            $totalLikes += $dayLikes;
            $currentDate->addDay();
        }

        return [
            'daily' => $dailyData,
            'total' => $totalLikes,
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::ARTICLE_REACTIONS;
    }
}
