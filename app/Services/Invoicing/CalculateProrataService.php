<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\DTOs\Invoicing\ProrataCalculationDTO;
use App\Models\FinancerModule;
use App\Models\FinancerUser;
use App\Support\RedisClusterHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

class CalculateProrataService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function calculateContractProrata(Carbon $contractDate, Carbon $periodStart, Carbon $periodEnd): float
    {
        /** @var float $value */
        $value = RedisClusterHelper::remember(
            sprintf(
                'prorata:contract:%s:%s:%s',
                $contractDate->toDateString(),
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ),
            self::CACHE_TTL_SECONDS,
            function () use ($contractDate, $periodStart, $periodEnd): float {
                if ($contractDate->lessThanOrEqualTo($periodStart)) {
                    return 1.0;
                }

                if ($contractDate->greaterThan($periodEnd)) {
                    return 0.0;
                }

                $effectiveStart = $contractDate->greaterThan($periodStart) ? $contractDate : $periodStart;
                $totalDays = $this->periodLengthInDays($periodStart, $periodEnd);
                $activeDays = $this->diffInDaysInclusive($effectiveStart, $periodEnd);

                return $this->ratio($activeDays, $totalDays);
            },
            ['prorata'],
            'prorata'
        );

        return $value;
    }

    /**
     * @return array<string, float>
     */
    public function calculateBeneficiaryProrata(string $financerId, Carbon $periodStart, Carbon $periodEnd): array
    {
        /** @var array<string, float> $value */
        $value = RedisClusterHelper::remember(
            sprintf(
                'prorata:beneficiaries:%s:%s:%s',
                $financerId,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ),
            self::CACHE_TTL_SECONDS,
            function () use ($financerId, $periodStart, $periodEnd): array {
                $records = FinancerUser::query()
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
                    })
                    ->get(['user_id', 'from', 'to']);

                $totalDays = $this->periodLengthInDays($periodStart, $periodEnd);
                if ($totalDays === 0) {
                    return [];
                }

                $results = [];

                foreach ($records as $record) {
                    $activationDate = Carbon::parse($record->from)->max($periodStart);
                    $deactivationDate = $record->to
                        ? Carbon::parse($record->to)->min($periodEnd)
                        : $periodEnd;

                    if ($activationDate->greaterThan($deactivationDate)) {
                        continue;
                    }

                    $activeDays = $this->diffInDaysInclusive($activationDate, $deactivationDate);
                    $results[$record->user_id] = $this->ratio($activeDays, $totalDays);
                }

                ksort($results);

                return $results;
            },
            ['prorata', 'financer:'.$financerId],
            'prorata'
        );

        return $value;
    }

    public function calculateModuleProrata(string $financerId, string $moduleId, Carbon $periodStart, Carbon $periodEnd): ProrataCalculationDTO
    {
        /** @var ProrataCalculationDTO $value */
        $value = RedisClusterHelper::remember(
            sprintf(
                'prorata:module:%s:%s:%s:%s',
                $financerId,
                $moduleId,
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ),
            self::CACHE_TTL_SECONDS,
            function () use ($financerId, $moduleId, $periodStart, $periodEnd): ProrataCalculationDTO {
                $pivot = FinancerModule::query()
                    ->where('financer_id', $financerId)
                    ->where('module_id', $moduleId)
                    ->first();

                if ($pivot === null) {
                    return new ProrataCalculationDTO(
                        percentage: 0.0,
                        days: 0,
                        totalDays: $this->periodLengthInDays($periodStart, $periodEnd),
                        periodStart: $periodStart->toDateString(),
                        periodEnd: $periodEnd->toDateString(),
                    );
                }

                $audits = DB::table('audits')
                    ->where('auditable_type', FinancerModule::class)
                    ->where('auditable_id', $pivot->id)
                    ->whereBetween('created_at', [
                        $periodStart->clone()->startOfDay(),
                        $periodEnd->clone()->endOfDay(),
                    ])
                    ->orderBy('created_at')
                    ->get(['old_values', 'new_values', 'created_at']);

                $activationDate = $this->resolveActivationDate($pivot->created_at?->clone() ?? $periodStart, $audits, $periodStart);
                $deactivationDate = $this->resolveDeactivationDate($audits);

                $effectiveStart = in_array($activationDate, [null, '', '0'], true) ? null : Carbon::parse($activationDate)->max($periodStart);
                $effectiveEnd = in_array($deactivationDate, [null, '', '0'], true) ? $periodEnd : Carbon::parse($deactivationDate)->min($periodEnd);

                if ($effectiveStart === null) {
                    if ($pivot->active) {
                        $effectiveStart = $periodStart->clone();
                    } else {
                        return new ProrataCalculationDTO(
                            percentage: 0.0,
                            days: 0,
                            totalDays: $this->periodLengthInDays($periodStart, $periodEnd),
                            periodStart: $periodStart->toDateString(),
                            periodEnd: $periodEnd->toDateString(),
                            activationDate: $activationDate,
                            deactivationDate: $deactivationDate,
                        );
                    }
                }

                if ($effectiveStart->greaterThan($effectiveEnd)) {
                    return new ProrataCalculationDTO(
                        percentage: 0.0,
                        days: 0,
                        totalDays: $this->periodLengthInDays($periodStart, $periodEnd),
                        periodStart: $periodStart->toDateString(),
                        periodEnd: $periodEnd->toDateString(),
                        activationDate: $activationDate,
                        deactivationDate: $deactivationDate,
                    );
                }

                $totalDays = $this->periodLengthInDays($periodStart, $periodEnd);
                $activeDays = $this->diffInDaysInclusive($effectiveStart, $effectiveEnd);

                return new ProrataCalculationDTO(
                    percentage: $this->ratio($activeDays, $totalDays),
                    days: $activeDays,
                    totalDays: $totalDays,
                    periodStart: $periodStart->toDateString(),
                    periodEnd: $periodEnd->toDateString(),
                    activationDate: $effectiveStart->toDateString(),
                    deactivationDate: $effectiveEnd->equalTo($periodEnd) && $deactivationDate === null
                        ? null
                        : $effectiveEnd->toDateString(),
                );
            },
            ['prorata', 'financer:'.$financerId],
            'prorata'
        );

        return $value;
    }

    private function periodLengthInDays(Carbon $start, Carbon $end): int
    {
        if ($start->greaterThan($end)) {
            return 0;
        }

        return $this->diffInDaysInclusive($start, $end);
    }

    private function diffInDaysInclusive(Carbon $start, Carbon $end): int
    {
        return (int) ($start->diffInDays($end) + 1);
    }

    private function ratio(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        $scaled = bcdiv((string) $numerator, (string) $denominator, 6);
        $value = round((float) $scaled, 2);

        if ($value > 1) {
            return 1.0;
        }

        if ($value < 0) {
            return 0.0;
        }

        return $value;
    }

    private function resolveActivationDate(?Carbon $pivotCreatedAt, $audits, Carbon $periodStart): ?string
    {
        foreach ($audits as $audit) {
            $old = $this->decodeAuditPayload($audit->old_values);
            $new = $this->decodeAuditPayload($audit->new_values);

            if (isset($old['active'], $new['active']) && $old['active'] === false && $new['active'] === true) {
                return Carbon::parse($audit->created_at)->toDateString();
            }
        }

        if ($pivotCreatedAt instanceof Carbon && $pivotCreatedAt->lessThanOrEqualTo($periodStart)) {
            return $periodStart->toDateString();
        }

        return null;
    }

    private function resolveDeactivationDate($audits): ?string
    {
        foreach ($audits->reverse() as $audit) {
            $old = $this->decodeAuditPayload($audit->old_values);
            $new = $this->decodeAuditPayload($audit->new_values);

            if (isset($old['active'], $new['active']) && $old['active'] === true && $new['active'] === false) {
                return Carbon::parse($audit->created_at)->toDateString();
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAuditPayload(?string $payload): array
    {
        if (in_array($payload, [null, '', 'null'], true)) {
            return [];
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}
