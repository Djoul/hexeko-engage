<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Integrations\HRTools\Models\Link;
use App\Models\EngagementLog;
use App\Models\FinancerUser;
use Carbon\Carbon;

class ShortcutsClicksCalculator extends BaseMetricCalculator
{
    /**
     * Calculate shortcuts clicks metric (LinkClicked)
     * Returns daily breakdown of clicks per shortcut type
     *
     * @return array{daily: array<int, array{date: string, total: int, shortcuts: array<string, int>}>, shortcuts: array<string, array{total: int, daily: array<int, array{date: string, count: int}>, link_id: string}>, total: int}
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
                'shortcuts' => [],
                'total' => 0,
            ];
        }

        // Get all unique link IDs in the period
        $linkIds = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'LinkClicked')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('target')
            ->distinct('target')
            ->pluck('target')
            ->map(function (mixed $target): string {
                // Convert to string and strip "link:" prefix if present
                if (is_string($target) && str_starts_with($target, 'link:')) {
                    return substr($target, 5);
                }

                return is_scalar($target) ? (string) $target : '';
            })
            ->filter(fn (string $id): bool => $id !== '') // Remove empty strings
            ->toArray();

        // Get Link models to map IDs to names
        $links = Link::whereIn('id', $linkIds)->get()->keyBy('id');

        // Initialize result structure
        $dailyData = [];
        $shortcutsData = [];
        $totalClicks = 0;
        $currentDate = $dateFrom->copy()->startOfDay();

        // Initialize shortcuts data structure using link names
        foreach ($linkIds as $linkId) {
            if (is_scalar($linkId)) {
                $link = $links->get($linkId);
                $linkIdString = (string) $linkId;
                $linkName = $link !== null && $link->name !== null ? $link->name : 'Unknown Link ('.$linkIdString.')';
                $shortcutsData[$linkName] = [
                    'total' => 0,
                    'daily' => [],
                    'link_id' => $linkIdString,
                ];
            }
        }

        // Load ALL engagement logs in a single query (fixes N+1 query issue)
        $allClicks = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'LinkClicked')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->whereNotNull('target')
            ->select('target', 'created_at')
            ->get()
            ->map(function (EngagementLog $log): EngagementLog {
                // Strip "link:" prefix from target if present
                if ($log->target !== null && str_starts_with($log->target, 'link:')) {
                    $log->target = substr($log->target, 5);
                }

                return $log;
            })
            ->groupBy(function (EngagementLog $log): string {
                return $log->created_at !== null ? $log->created_at->toDateString() : now()->toDateString();
            });

        // Process each day in memory (no more DB queries in loop)
        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();
            $dayClicks = $allClicks->get($dateKey) ?? collect();

            // Group by target (link_id) for this day
            $dayClicksByTarget = $dayClicks->groupBy('target')->map(function ($clicks) {
                return $clicks->count();
            });

            $dayTotal = 0;
            $dayShortcuts = [];

            foreach ($shortcutsData as $linkName => $data) {
                $linkId = $data['link_id'];
                $clicks = $dayClicksByTarget->get($linkId, 0);
                $dayShortcuts[$linkName] = $clicks;
                $shortcutsData[$linkName]['daily'][] = [
                    'date' => $dateKey,
                    'count' => $clicks,
                ];
                $shortcutsData[$linkName]['total'] += $clicks;
                $dayTotal += $clicks;
            }

            $dailyData[] = [
                'date' => $dateKey,
                'total' => $dayTotal,
                'shortcuts' => $dayShortcuts,
            ];

            $totalClicks += $dayTotal;
            $currentDate->addDay();
        }

        return [
            'daily' => $dailyData,
            'shortcuts' => $shortcutsData,
            'total' => $totalClicks,
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::SHORTCUTS_CLICKS;
    }
}
