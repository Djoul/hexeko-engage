<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\EngagementLog;
use App\Models\FinancerUser;
use App\Models\Module;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ModuleUsageCalculator extends BaseMetricCalculator
{
    /**
     * Calculate module usage metric
     * Returns daily breakdown of module usage
     *
     * @return array{daily: array<int, array{date: string, modules: array<string, int>}>, modules: array<string, int>, total: int, moduleNames: Collection<string, Module>}
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
                'modules' => [],
                'total' => 0,
            ];
        }

        // Get all modules for name mapping
        $modules = Module::all()->keyBy('id');

        // Load ALL module access logs in a single query (fixes N+1 query issue)
        $allModuleAccesses = EngagementLog::whereIn('user_id', $financerUserIds)
            ->where('type', 'ModuleAccessed')
            ->whereBetween('created_at', [$dateFrom->copy()->startOfDay(), $dateTo->copy()->endOfDay()])
            ->whereNotNull('target')
            ->get()
            ->groupBy(function ($log) {
                return $log->created_at !== null ? $log->created_at->toDateString() : now()->toDateString();
            });

        // Initialize result structure
        $dailyData = [];
        $moduleStats = [];
        $currentDate = $dateFrom->copy()->startOfDay();

        // Process each day in memory (no more DB queries in loop)
        while ($currentDate <= $dateTo) {
            $dateKey = $currentDate->toDateString();

            // Get events for this day from pre-loaded data
            $dayEvents = $allModuleAccesses->get($dateKey) ?? collect();

            // Track module usage per session for this day
            $dayModuleUsage = [];

            foreach ($dayEvents as $event) {
                $moduleId = (string) $event->target;
                $userId = $event->user_id;
                $sessionId = $event->metadata['session_id'] ?? null;

                // Skip if module not found
                if (! $modules->has($moduleId)) {
                    continue;
                }

                if (! array_key_exists($moduleId, $dayModuleUsage)) {
                    $dayModuleUsage[$moduleId] = [];
                }

                // Count unique sessions per module
                if ($sessionId) {
                    $sessionKey = $userId.'_'.$sessionId;
                    $dayModuleUsage[$moduleId][$sessionKey] = true;
                } else {
                    // If no session_id, use event ID as unique key
                    $dayModuleUsage[$moduleId][$event->id] = true;
                }
            }

            // Count usage for each module this day
            $dayStats = [];
            foreach ($dayModuleUsage as $moduleId => $sessions) {
                $count = count($sessions);
                $dayStats[$moduleId] = $count;

                // Update total stats for this module
                if (! array_key_exists($moduleId, $moduleStats)) {
                    $moduleStats[$moduleId] = 0;
                }
                $moduleStats[$moduleId] += $count;
            }

            $dailyData[] = [
                'date' => $currentDate->toDateString(),
                'modules' => $dayStats,
            ];

            $currentDate->addDay();
        }

        // Calculate total usage across all modules
        $totalUsage = array_sum($moduleStats);

        return [
            'daily' => $dailyData,
            'modules' => $moduleStats,
            'total' => $totalUsage,
            'moduleNames' => $modules, // Include module objects for name lookup
        ];
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::MODULE_USAGE;
    }
}
