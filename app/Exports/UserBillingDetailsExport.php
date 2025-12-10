<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\FinancerUser;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserBillingDetailsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
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
            return FinancerUser::query()->whereRaw('1 = 0');
        }

        return FinancerUser::query()
            ->join('users', 'financer_user.user_id', '=', 'users.id')
            ->where('financer_user.financer_id', $financerId)
            ->where('financer_user.active', true)
            ->where(function ($query): void {
                $query
                    ->where('financer_user.from', '<=', $this->periodEnd)
                    ->where(function ($inner): void {
                        $inner
                            ->whereNull('financer_user.to')
                            ->orWhere('financer_user.to', '>=', $this->periodStart);
                    });
            })
            ->select([
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'financer_user.from',
                'financer_user.to',
                'financer_user.financer_id', // Need this to get pivot data
                'financer_user.user_id', // Need this to get pivot data
            ])
            ->orderBy('users.email');
    }

    public function headings(): array
    {
        return [
            'User Name',
            'Email',
            'Period From',
            'Period To',
            'Active Days',
            'Total Days',
            'Prorata',
            'Unit Price (€)',
            'User Amount (€)',
        ];
    }

    /**
     * @param  FinancerUser  $row
     * @return array<int, string>
     */
    public function map($row): array
    {
        // Calculate effective dates (clamped to billing period)
        $userStart = Carbon::parse($row->from)->max($this->periodStart);
        $userEnd = $row->to !== null
            ? Carbon::parse($row->to)->min($this->periodEnd)
            : $this->periodEnd;

        // Calculate days
        $activeDays = $this->diffInDaysInclusive($userStart, $userEnd);
        $totalDays = $this->diffInDaysInclusive($this->periodStart, $this->periodEnd);

        // Calculate prorata
        $prorata = $totalDays > 0 ? $activeDays / $totalDays : 0.0;

        // Get unit price from financer's core package price
        $unitPrice = $this->invoice->recipient?->core_package_price ?? 0;

        // Calculate user amount
        $userAmount = $unitPrice * $prorata;

        return [
            trim($row->first_name.' '.$row->last_name), // User Name
            $row->email, // Email
            $userStart->format('Y-m-d'), // Period From
            $userEnd->format('Y-m-d'), // Period To
            $activeDays, // Active Days
            $totalDays, // Total Days
            number_format($prorata, 2, '.', ''), // Prorata
            number_format($unitPrice / 100, 2, '.', ''), // Unit Price (€)
            number_format($userAmount / 100, 2, '.', ''), // User Amount (€)
        ];
    }

    private function diffInDaysInclusive(Carbon $start, Carbon $end): int
    {
        return (int) ($start->diffInDays($end) + 1);
    }
}
