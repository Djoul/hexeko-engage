<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\Models\FinancerModule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JsonException;

class GetModuleActivationHistoryService
{
    /**
     * @return array<int, array{event: string, at: string}>
     */
    public function getActivationHistory(string $financerId, string $moduleId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $pivot = $this->findPivot($financerId, $moduleId);

        if (! $pivot instanceof FinancerModule) {
            return [];
        }

        $audits = $this->queryAudits($pivot->id, $periodStart, $periodEnd);

        return $audits
            ->map(function (object $audit): ?array {
                $event = $this->determineEvent($audit);

                if ($event === null) {
                    return null;
                }

                return [
                    'event' => $event,
                    'at' => Carbon::parse($audit->created_at)->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function isModuleActiveInPeriod(string $financerId, string $moduleId, Carbon $date): bool
    {
        $pivot = $this->findPivot($financerId, $moduleId);

        if (! $pivot instanceof FinancerModule) {
            return false;
        }

        $audits = $this->queryAllAudits($pivot->id);

        $state = $this->inferInitialState($audits, (bool) $pivot->active);

        foreach ($audits as $audit) {
            $createdAt = Carbon::parse($audit->created_at);

            if ($createdAt->greaterThan($date)) {
                break;
            }

            $new = $this->decodePayload($audit->new_values);

            if (array_key_exists('active', $new)) {
                $state = (bool) $new['active'];
            }
        }

        return $state;
    }

    private function findPivot(string $financerId, string $moduleId): ?FinancerModule
    {
        return FinancerModule::query()
            ->where('financer_id', $financerId)
            ->where('module_id', $moduleId)
            ->first();
    }

    private function queryAudits(string $pivotId, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        return DB::table('audits')
            ->where('auditable_type', FinancerModule::class)
            ->where('auditable_id', $pivotId)
            ->whereBetween('created_at', [
                $periodStart->clone()->startOfDay(),
                $periodEnd->clone()->endOfDay(),
            ])
            ->orderBy('created_at')
            ->get(['old_values', 'new_values', 'created_at']);
    }

    private function queryAllAudits(string $pivotId): Collection
    {
        return DB::table('audits')
            ->where('auditable_type', FinancerModule::class)
            ->where('auditable_id', $pivotId)
            ->orderBy('created_at')
            ->get(['old_values', 'new_values', 'created_at']);
    }

    private function determineEvent(object $audit): ?string
    {
        $old = $this->decodePayload($audit->old_values);
        $new = $this->decodePayload($audit->new_values);

        if (array_key_exists('active', $old) && array_key_exists('active', $new)) {
            if ($old['active'] === false && $new['active'] === true) {
                return 'activated';
            }

            if ($old['active'] === true && $new['active'] === false) {
                return 'deactivated';
            }
        }

        return null;
    }

    private function inferInitialState(Collection $audits, bool $default): bool
    {
        $first = $audits->first();

        if ($first === null) {
            return $default;
        }

        $old = $this->decodePayload($first->old_values);

        if (array_key_exists('active', $old)) {
            return (bool) $old['active'];
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(?string $payload): array
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
