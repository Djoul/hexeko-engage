<?php

namespace App\Services;

use App\Models\EngagementLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EngagementMetricsService
{
    /**
     * Calculate metrics for a date range
     *
     * @param  Carbon  $from  Start date
     * @param  Carbon  $to  End date
     * @return array<string, array<string, int>> Metrics data
     */
    public function calculateRangeMetrics(Carbon $from, Carbon $to): array
    {
        return [
            'modules' => $this->moduleUsageStats($from, $to),
            'tools' => $this->toolClicks($from, $to),
            'articles' => $this->articleViews($from, $to),
        ];
    }

    /**
     * Get module usage statistics
     *
     * @param  Carbon|null  $from  Start date (optional)
     * @param  Carbon|null  $to  End date (optional)
     * @return array<string, int> Module usage counts by module name
     */
    public function moduleUsageStats(?Carbon $from = null, ?Carbon $to = null): array
    {
        $result = EngagementLog::query()
            ->where('type', 'ModuleUsed')
            ->when($from, fn ($q) => $q->where('logged_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('logged_at', '<=', $to))
            ->select('target', DB::raw('count(*) as total'))
            ->groupBy('target')
            ->pluck('total', 'target')
            ->toArray();

        // Ensure all values are integers
        $integerResult = [];
        foreach ($result as $key => $value) {
            if ($value === null) {
                $integerResult[$key] = 0;
            } elseif (is_numeric($value)) {
                $integerResult[$key] = (int) $value;
            } else {
                $integerResult[$key] = 0; // Default to 0 for non-numeric values
            }
        }

        return $integerResult;
    }

    /**
     * Get tool click statistics
     *
     * @param  Carbon|null  $from  Start date (optional)
     * @param  Carbon|null  $to  End date (optional)
     * @return array<string, int> Tool click counts by tool name
     */
    public function toolClicks(?Carbon $from = null, ?Carbon $to = null): array
    {
        $result = EngagementLog::query()
            ->where('type', 'ToolClicked')
            ->when($from, fn ($q) => $q->where('logged_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('logged_at', '<=', $to))
            ->select('target', DB::raw('count(*) as total'))
            ->groupBy('target')
            ->pluck('total', 'target')
            ->toArray();

        // Ensure all values are integers
        $integerResult = [];
        foreach ($result as $key => $value) {
            if ($value === null) {
                $integerResult[$key] = 0;
            } elseif (is_numeric($value)) {
                $integerResult[$key] = (int) $value;
            } else {
                $integerResult[$key] = 0; // Default to 0 for non-numeric values
            }
        }

        return $integerResult;
    }

    /**
     * Get article view statistics
     *
     * @param  Carbon|null  $from  Start date (optional)
     * @param  Carbon|null  $to  End date (optional)
     * @return array<string, int> Article view counts by article ID
     */
    public function articleViews(?Carbon $from = null, ?Carbon $to = null): array
    {
        $result = EngagementLog::query()
            ->where('type', 'ArticleViewed')
            ->when($from, fn ($q) => $q->where('logged_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('logged_at', '<=', $to))
            ->select('target', DB::raw('count(*) as total'))
            ->groupBy('target')
            ->pluck('total', 'target')
            ->toArray();

        // Ensure all values are integers
        $integerResult = [];
        foreach ($result as $key => $value) {
            if ($value === null) {
                $integerResult[$key] = 0;
            } elseif (is_numeric($value)) {
                $integerResult[$key] = (int) $value;
            } else {
                $integerResult[$key] = 0; // Default to 0 for non-numeric values
            }
        }

        return $integerResult;
    }

    public function bounceRateForPage(string $slug, ?Carbon $from = null, ?Carbon $to = null): float
    {
        $views = EngagementLog::query()
            ->where('type', 'ArticleViewed')
            ->where('target', $slug)
            ->when($from, fn ($q) => $q->where('logged_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('logged_at', '<=', $to))
            ->count();

        $bounces = EngagementLog::query()
            ->where('type', 'ArticleClosedWithoutInteraction')
            ->where('target', $slug)
            ->when($from, fn ($q) => $q->where('logged_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('logged_at', '<=', $to))
            ->count();

        return $views > 0 ? round(($bounces / $views) * 100, 2) : 0.0;
    }
}
