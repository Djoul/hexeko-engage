<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Aggregates\DivisionBalanceAggregate;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\Financer;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class MarkInvoiceAsPaidAction
{
    public function execute(Invoice $invoice, int $amountPaid): InvoiceDTO
    {
        if ($amountPaid <= 0) {
            throw new InvalidArgumentException('Amount paid must be greater than zero.');
        }

        if ($invoice->status === InvoiceStatus::PAID) {
            throw new LogicException('Invoice is already paid.');
        }

        $divisionId = $this->resolveDivisionId($invoice);

        DB::transaction(function () use ($invoice, $amountPaid, $divisionId): void {
            $invoice->update([
                'status' => InvoiceStatus::PAID,
                'paid_at' => Carbon::now(),
            ]);

            if ($divisionId !== null) {
                DivisionBalanceAggregate::retrieve($divisionId)
                    ->invoicePaid($divisionId, $invoice->id, $amountPaid, Carbon::now())
                    ->persist();
            }
        });

        $refreshedInvoice = $invoice->fresh('items');
        if (! $refreshedInvoice instanceof Invoice) {
            throw new LogicException('Failed to refresh invoice after marking as paid.');
        }

        return InvoiceDTO::fromModel($refreshedInvoice);
    }

    private function resolveDivisionId(Invoice $invoice): ?string
    {
        if ($invoice->recipient_type === 'division') {
            return $invoice->recipient_id;
        }

        if ($invoice->recipient_type === 'financer') {
            $financer = Financer::find($invoice->recipient_id);

            return $financer?->division_id;
        }

        return null;
    }
}
