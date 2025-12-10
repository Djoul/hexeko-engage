<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\EngagementLog;
use App\Models\FinancerUser;
use Carbon\Carbon;
use Carbon\CarbonInterval;

class SessionTimeCalculator extends BaseMetricCalculator
{
    /**
     * Calculate session time metric
     *
     * @return array{total: float, daily: array<int, array{date: string, count: float}>}
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
                'total' => 0,
                'daily' => [],
            ];
        }

        // Load ALL session logs in a single query (fixes N+1 query issue)
        $allSessionStarts = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'SessionStarted')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at !== null ? $log->created_at->toDateString() : now()->toDateString();
            });

        $allSessionEnds = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'SessionFinished')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at !== null ? $log->created_at->toDateString() : now()->toDateString();
            });

        // Process day by day in memory (no more DB queries in loop)
        $dailyStats = [];
        $currentDate = $dateFrom->copy()->startOfDay();
        $allDurations = [];

        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();

            // Get sessions for this day from pre-loaded data
            $daySessionStarts = ($allSessionStarts->get($dateKey) ?? collect())
                ->keyBy(function ($log): string {
                    /** @var array<string, mixed> $metadata */
                    $metadata = $log->metadata ?? [];
                    $sessionId = $metadata['session_id'] ?? ($log->created_at !== null ? $log->created_at->timestamp : time());

                    $sessionIdString = is_scalar($sessionId) ? (string) $sessionId : (string) time();

                    return $log->user_id.'_'.$sessionIdString;
                });

            $daySessionEnds = $allSessionEnds->get($dateKey) ?? collect();

            // Calculate durations for this day
            $dayDurations = [];

            foreach ($daySessionEnds as $sessionEnd) {
                $sessionId = $sessionEnd->metadata['session_id'] ?? null;

                // Try to find matching session start
                $key = $sessionEnd->user_id.'_'.($sessionId ?? ($sessionEnd->created_at !== null ? $sessionEnd->created_at->timestamp : time()));
                $sessionStart = $daySessionStarts->get($key);

                // If we have metadata duration, use it directly
                /** @var array<string, mixed> $endMetadata */
                $endMetadata = $sessionEnd->metadata ?? [];
                if (array_key_exists('duration', $endMetadata)) {
                    $duration = is_numeric($endMetadata['duration']) ? (int) $endMetadata['duration'] : 0;
                    if ($duration >= 5 && $duration <= 28800) {
                        $dayDurations[] = $duration;
                        $allDurations[] = $duration;
                    }
                } elseif ($sessionStart && $sessionStart->created_at !== null && $sessionEnd->created_at !== null) {
                    // Calculate duration from timestamps
                    $duration = $sessionStart->created_at->diffInSeconds($sessionEnd->created_at);
                    if ($duration >= 5 && $duration <= 28800) {
                        $dayDurations[] = $duration;
                        $allDurations[] = $duration;
                    }
                }
            }

            // Calculate average for this day
            $dayAverage = 0;
            if ($dayDurations !== []) {
                $dayAverage = round(array_sum($dayDurations) / count($dayDurations) / 60, 1); // Convert to minutes
            }

            $dailyStats[] = [
                'date' => $currentDate->toDateString(),
                'count' => $dayAverage,
            ];

            $currentDate->addDay();
        }

        // Calculate overall median
        $overallMedian = 0;
        $formattedMedian = '0s';
        if ($allDurations !== []) {
            sort($allDurations);
            $count = count($allDurations);
            $overallMedian = $count % 2 === 0
                ? ($allDurations[$count / 2 - 1] + $allDurations[$count / 2]) / 2
                : $allDurations[intval($count / 2)];

            // Convert to human-readable format using CarbonInterval
            $interval = CarbonInterval::seconds((int) $overallMedian);

            $formattedMedian = $interval->cascade()->forHumans(syntax: ['join' => ''], short: true);
        }

        return [
            'total' => is_string($formattedMedian) ? (float) 0 : $formattedMedian,
            'daily' => array_values($dailyStats),
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::SESSION_TIME;
    }
}
