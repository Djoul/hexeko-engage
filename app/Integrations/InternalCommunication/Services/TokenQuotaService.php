<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Services;

use App\Models\CreditBalance;
use App\Models\Financer;

class TokenQuotaService
{
    /**
     * Get AI token quota information for a financer.
     *
     * @return array{total: int, consumed: int, remaining: int, percentage_used: float}
     */
    public function getQuotaForFinancer(string $financerId): array
    {
        $initialTokenAmount = config('ai.initial_token_amount');

        $creditBalance = CreditBalance::where([
            'owner_type' => Financer::class,
            'owner_id' => $financerId,
            'type' => 'ai_token',
        ])->first();

        if ($creditBalance && $creditBalance->owner_type === Financer::class && $creditBalance->owner_id === $financerId) {
            $creditBalance->setRelation('owner', Financer::with('division')->find($financerId));
        }

        if (! $creditBalance) {
            // Return default values when no balance exists
            return [
                'division_id' => null,
                'division_name' => null,
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
                'percentage_used' => 0.0,
            ];
        }

        $division = $creditBalance->division;
        $context = $creditBalance->context ?? [];
        $total = $context['initial_quota'] ?? $initialTokenAmount;
        $remaining = $creditBalance->balance;
        $consumed = $total - $remaining;

        // Calculate percentage used, avoid division by zero
        $percentageUsed = $total > 0 ? (($consumed / $total) * 100) : 0.0;

        return [
            'division_id' => $division?->id,
            'division_name' => $division?->name,
            'total' => $total, // initiale 1000 000 convention
            'consumed' => $consumed, // really used
            'remaining' => $remaining, // total - consumed
            'percentage_used' => round($percentageUsed, 1), // percentage consumed
        ];
    }
}
