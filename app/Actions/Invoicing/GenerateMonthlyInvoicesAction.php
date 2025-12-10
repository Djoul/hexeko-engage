<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Aggregates\InvoiceGenerationAggregate;
use App\DTOs\Invoicing\InvoiceBatchDTO;
use App\Models\Division;
use App\Models\Financer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class GenerateMonthlyInvoicesAction
{
    public function __construct(
        private readonly GenerateDivisionInvoiceAction $generateDivisionInvoiceAction,
        private readonly GenerateFinancerInvoiceAction $generateFinancerInvoiceAction,
    ) {}

    public function execute(string $monthYear, ?string $divisionId, ?string $financerId, bool $dryRun = false): InvoiceBatchDTO
    {
        $periodStart = Carbon::createFromFormat('Y-m', $monthYear);
        if (! $periodStart instanceof Carbon) {
            throw new LogicException("Invalid month-year format: {$monthYear}");
        }
        $periodStart->startOfMonth();
        $periodStart->clone()->endOfMonth();

        $divisions = $this->resolveDivisions($divisionId);
        $financers = $this->resolveFinancers($financerId, $divisions);

        $totalInvoices = $divisions->count() + $financers->count();

        if ($dryRun) {
            return new InvoiceBatchDTO(
                batchId: Str::uuid()->toString(),
                monthYear: $monthYear,
                totalInvoices: $totalInvoices,
                status: 'dry_run',
            );
        }

        $batchId = Str::uuid()->toString();

        DB::transaction(function () use ($batchId, $monthYear, $divisions, $financers): void {
            InvoiceGenerationAggregate::retrieve($batchId)
                ->batchStarted($batchId, $monthYear, $divisions->count() + $financers->count(), Carbon::now())
                ->persist();

            foreach ($divisions as $division) {
                $this->generateDivisionInvoiceAction->execute(
                    divisionId: $division->id,
                    monthYear: $monthYear,
                    batchId: $batchId,
                );
            }

            foreach ($financers as $financer) {
                // Skip financers without beneficiaries (returns null)
                $this->generateFinancerInvoiceAction->execute(
                    financerId: $financer->id,
                    monthYear: $monthYear,
                    batchId: $batchId,
                );
            }

            InvoiceGenerationAggregate::retrieve($batchId)
                ->batchCompleted($batchId, Carbon::now())
                ->persist();
        });

        return new InvoiceBatchDTO(
            batchId: $batchId,
            monthYear: $monthYear,
            totalInvoices: $totalInvoices,
            status: 'completed',
        );
    }

    /**
     * @return Collection<int, Division>
     */
    private function resolveDivisions(?string $divisionId): Collection
    {
        $query = Division::query();

        if ($divisionId !== null) {
            $query->where('id', $divisionId);
        }

        return $query->with('financers')->get();
    }

    /**
     * @param  Collection<int, Division>  $divisions
     * @return Collection<int, Financer>
     */
    private function resolveFinancers(?string $financerId, Collection $divisions): Collection
    {
        if ($financerId !== null) {
            return Financer::query()->where('id', $financerId)->get();
        }

        return $divisions->flatMap(static fn (Division $division) => $division->financers);
    }
}
