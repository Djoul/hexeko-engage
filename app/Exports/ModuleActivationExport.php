<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Invoice;
use App\Models\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ModuleActivationExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    private Invoice $invoice;

    private Carbon $periodStart;

    private Carbon $periodEnd;

    public function __construct(private readonly string $invoiceId)
    {
        $this->invoice = Invoice::with('recipient')->findOrFail($this->invoiceId);
        $this->periodStart = $this->invoice->billing_period_start;
        $this->periodEnd = $this->invoice->billing_period_end;
    }

    public function query(): Builder
    {
        // Get financer_id from invoice recipient (polymorphic)
        $financerId = $this->invoice->recipient_type === 'App\\Models\\Financer'
            ? $this->invoice->recipient_id
            : null;

        if ($financerId === null) {
            // If invoice recipient is not a Financer, return empty query
            return Module::query()->whereRaw('1 = 0');
        }

        return Module::query()
            ->join('financer_module', 'modules.id', '=', 'financer_module.module_id')
            ->where('financer_module.financer_id', $financerId)
            ->leftJoin('invoice_items', function ($join): void {
                $join->on('modules.id', '=', 'invoice_items.module_id')
                    ->where('invoice_items.invoice_id', '=', $this->invoiceId);
            })
            ->select([
                'modules.*',
                'financer_module.active',
                'financer_module.created_at as activation_date',
                'invoice_items.unit_price_htva',
            ])
            ->orderByRaw("modules.name->>'en'");
    }

    public function headings(): array
    {
        return [
            'Module Name',
            'Activation Date',
            'Deactivation Date',
            'Active Days',
            'Total Days',
            'Prorata',
            'Unit Price (€)',
            'Module Amount (€)',
        ];
    }

    /**
     * @param  Module  $row
     * @return array<int, string|int|null>
     */
    public function map($row): array
    {
        // Get activation date (clamped to billing period start)
        $activationDate = Carbon::parse($row->activation_date);
        $effectiveStart = $activationDate->max($this->periodStart);

        // Deactivation date logic:
        // - If module is currently inactive (active=false), we would need audit trail
        // - For now, if active=true, deactivation is null
        // - If active=false, we assume deactivation happened during this period (end of period)
        $deactivationDate = null;
        $effectiveEnd = $this->periodEnd;

        if (! $row->active) {
            // Module is deactivated - use period end as deactivation date
            $deactivationDate = $this->periodEnd->format('Y-m-d');
            $effectiveEnd = $this->periodEnd;
        }

        // Calculate days
        $activeDays = $this->diffInDaysInclusive($effectiveStart, $effectiveEnd);
        $totalDays = $this->diffInDaysInclusive($this->periodStart, $this->periodEnd);

        // Calculate prorata
        $prorata = $totalDays > 0 ? $activeDays / $totalDays : 0.0;

        // Get unit price from invoice item (if exists)
        $unitPrice = $row->unit_price_htva ?? 0;

        // Calculate module amount
        $moduleAmount = $unitPrice * $prorata;

        // Get module name using Spatie Translatable's getTranslation method
        // Fallback to en-US if en is not available
        $moduleName = $row->getTranslation('name', 'en', false)
            ?: $row->getTranslation('name', 'en-US', false)
            ?: '';

        return [
            $moduleName, // Module Name (get English translation)
            $effectiveStart->format('Y-m-d'), // Activation Date
            $deactivationDate, // Deactivation Date (null if still active)
            $activeDays, // Active Days
            $totalDays, // Total Days
            number_format($prorata, 2, '.', ''), // Prorata
            number_format($unitPrice / 100, 2, '.', ''), // Unit Price (€)
            number_format($moduleAmount / 100, 2, '.', ''), // Module Amount (€)
        ];
    }

    private function diffInDaysInclusive(Carbon $start, Carbon $end): int
    {
        return (int) ($start->diffInDays($end) + 1);
    }
}
