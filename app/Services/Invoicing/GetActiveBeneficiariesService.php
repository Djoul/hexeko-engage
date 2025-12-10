<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\FinancerUser;
use Illuminate\Support\Carbon;

class GetActiveBeneficiariesService
{
    public function __construct(private readonly CalculateProrataService $calculateProrataService) {}

    public function getActiveBeneficiariesCount(string $financerId, Carbon $periodStart, Carbon $periodEnd): int
    {
        $query = FinancerUser::query()
            ->where('financer_id', $financerId)
            ->where('active', true)
            ->where(function ($query) use ($periodStart, $periodEnd): void {
                $query
                    ->where('from', '<=', $periodEnd)
                    ->where(function ($inner) use ($periodStart): void {
                        $inner
                            ->whereNull('to')
                            ->orWhere('to', '>=', $periodStart);
                    });
            });

        return $query->count('user_id');
    }

    /**
     * @return array<string, float>
     */
    public function getActiveBeneficiariesWithProrata(string $financerId, Carbon $periodStart, Carbon $periodEnd): array
    {
        return $this->calculateProrataService->calculateBeneficiaryProrata(
            $financerId,
            $periodStart,
            $periodEnd
        );
    }
}
