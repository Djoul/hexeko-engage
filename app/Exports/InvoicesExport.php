<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Invoice>
 */
class InvoicesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        private readonly array $filters = [],
        private readonly ?User $user = null
    ) {}

    public function query(): Builder
    {
        $query = Invoice::query()
            ->with('recipient');

        // Appliquer le scope accessibleByUser si un utilisateur est fourni
        if ($this->user instanceof User) {
            // @phpstan-ignore-next-line (scope dynamique Laravel)
            $query->accessibleByUser($this->user);
        }

        return $query
            ->when(
                Arr::exists($this->filters, 'status'),
                fn (Builder $query): Builder => $query->where('status', $this->filters['status'])
            )
            ->when(
                Arr::exists($this->filters, 'recipient_type'),
                fn (Builder $query): Builder => $query->where('recipient_type', $this->filters['recipient_type'])
            )
            ->when(
                Arr::exists($this->filters, 'recipient_id'),
                fn (Builder $query): Builder => $query->where('recipient_id', $this->filters['recipient_id'])
            )
            ->when(
                Arr::exists($this->filters, 'issuer_type'),
                fn (Builder $query): Builder => $query->where('issuer_type', $this->filters['issuer_type'])
            )
            ->when(
                Arr::exists($this->filters, 'issuer_id'),
                fn (Builder $query): Builder => $query->where('issuer_id', $this->filters['issuer_id'])
            )
            ->when(
                Arr::exists($this->filters, 'date_start'),
                fn (Builder $query): Builder => $query->where('billing_period_start', '>=', $this->filters['date_start'])
            )
            ->when(
                Arr::exists($this->filters, 'date_end'),
                fn (Builder $query): Builder => $query->where('billing_period_end', '<=', $this->filters['date_end'])
            )
            ->orderByDesc('created_at');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Invoice Number',
            'Recipient',
            'Status',
            'Subtotal (HTVA)',
            'VAT Amount',
            'Total (TTC)',
            'Billing Period Start',
            'Billing Period End',
            'Due Date',
        ];
    }

    /**
     * @param  Invoice  $invoice
     * @return array<int, string>
     */
    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->recipient?->name ?? $invoice->recipient_id,
            $invoice->status,
            number_format($invoice->subtotal_htva / 100, 2, '.', ''),
            number_format($invoice->vat_amount / 100, 2, '.', ''),
            number_format($invoice->total_ttc / 100, 2, '.', ''),
            $invoice->billing_period_start?->format('Y-m-d') ?? '',
            $invoice->billing_period_end?->format('Y-m-d') ?? '',
            $invoice->due_date?->format('Y-m-d') ?? '',
        ];
    }
}
