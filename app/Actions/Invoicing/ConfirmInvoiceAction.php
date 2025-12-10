<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use LogicException;

class ConfirmInvoiceAction
{
    public function execute(Invoice $invoice): InvoiceDTO
    {
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new LogicException('Only draft invoices can be confirmed.');
        }

        $invoice->update([
            'status' => InvoiceStatus::CONFIRMED,
            'confirmed_at' => Carbon::now(),
        ]);

        $refreshedInvoice = $invoice->fresh('items');
        if (! $refreshedInvoice instanceof Invoice) {
            throw new LogicException('Failed to refresh invoice after confirmation.');
        }

        return InvoiceDTO::fromModel($refreshedInvoice);
    }
}
