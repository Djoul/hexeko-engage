<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\EngagementLog;
use App\Models\FinancerUser;
use Carbon\Carbon;

class BounceRateCalculator extends BaseMetricCalculator
{
    /**
     * Calculate bounce rate metric
     *
     * @return array{bounce_rate: float, total_sessions: int, bounce_sessions: int}
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
                'bounce_rate' => 0,
                'total_sessions' => 0,
                'bounce_sessions' => 0,
            ];
        }

        // Get total sessions
        $totalSessions = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'SessionStarted')
            ->whereBetween('logged_at', [$dateFrom, $dateTo])
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
            ->whereBetween('logged_at', [$dateFrom, $dateTo])
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
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::BOUNCE_RATE;
    }
}
