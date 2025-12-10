<?php

namespace App\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Contracts\MetricCalculatorInterface;
use Carbon\Carbon;

class ActivationRateCalculator implements MetricCalculatorInterface
{
    /**
     * Calculate activation rate metric
     *
     * @return array{total: float, daily: array<int, array{date: string, count: float}>}
     */
    public function calculate(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): array {
        // Get all invited users up to the end date (new User refactor architecture)
        $allInvitedUsers = User::query()
            ->whereNotNull('invited_at')
            ->where('invited_at', '<=', $dateTo)
            ->whereHas('financers', fn ($q) => $q->where('financer_id', $financerId))
            ->get();

        // Get all activated users up to the end date
        $allRegisteredUsers = FinancerUser::where('financer_id', $financerId)
            ->where('created_at', '<=', $dateTo)
            ->whereActive(true)
            ->whereHas('user', fn ($q) => $q->whereNotNull('invited_at')) // Only count invited users
            ->get();

        // If no invited users, use all financer users as base (100% activation)
        if ($allInvitedUsers->isEmpty() && $allRegisteredUsers->isNotEmpty()) {
            // All users are considered active since there's no invitation process
            $dailyStats = [];
            $currentDate = $dateFrom->copy()->startOfDay();

            while ($currentDate <= $dateTo) {
                $registeredUpToDate = $allRegisteredUsers
                    ->filter(fn ($user): bool => $user->created_at !== null && $user->created_at <= $currentDate->endOfDay())
                    ->count();

                $dailyStats[] = [
                    'date' => $currentDate->toDateString(),
                    'count' => $registeredUpToDate > 0 ? 100.0 : 0.0, // 100% if any users exist
                ];

                $currentDate->addDay();
            }

            return [
                'total' => 100.0,
                'daily' => $dailyStats,
            ];
        }

        // If no users at all
        if ($allInvitedUsers->isEmpty() && $allRegisteredUsers->isEmpty()) {
            return [
                'total' => 0.0,
                'daily' => [],
            ];
        }

        // Calculate daily activation rates
        $dailyStats = [];
        $currentDate = $dateFrom->copy()->startOfDay();

        while ($currentDate <= $dateTo) {
            // Count invited users up to this date
            $invitedUpToDate = $allInvitedUsers
                ->filter(fn (User $user): bool => $user->invited_at !== null && $user->invited_at <= $currentDate->endOfDay())
                ->count();

            // Count activated users up to this date
            $registeredUpToDate = $allRegisteredUsers
                ->filter(fn (FinancerUser $fu): bool => $fu->created_at !== null && $fu->created_at <= $currentDate->endOfDay())
                ->count();

            // Calculate rate
            $rate = $invitedUpToDate > 0
                ? round(($registeredUpToDate / $invitedUpToDate) * 100, 1)
                : 0.0;

            $dailyStats[] = [
                'date' => $currentDate->toDateString(),
                'count' => $rate, // Using 'count' to match ActiveBeneficiariesCalculator structure
            ];

            $currentDate->addDay();
        }

        // Calculate final rate
        $totalInvited = $allInvitedUsers->count();
        $totalRegistered = $allRegisteredUsers->count();
        $finalRate = round(($totalRegistered / $totalInvited) * 100, 1);

        return [
            'total' => $finalRate,
            'daily' => $dailyStats,
        ];
    }

    /**
     * Get cache key for this calculation
     */
    public function getCacheKey(
        string $financerId,
        Carbon $dateFrom,
        Carbon $dateTo,
        string $period
    ): string {
        return sprintf(
            'metrics:%s:%s:%s:%s:%s',
            $this->getMetricType(),
            $financerId,
            $dateFrom->toDateString(),
            $dateTo->toDateString(),
            $period
        );
    }

    /**
     * Get the metric type
     */
    public function getMetricType(): string
    {
        return FinancerMetricType::ACTIVATION_RATE;
    }

    /**
     * Get cache TTL in seconds
     */
    public function getCacheTTL(): int
    {
        return 3600; // 1 hour
    }

    /**
     * Whether this calculator supports aggregation
     */
    public function supportsAggregation(): bool
    {
        return true;
    }
}
