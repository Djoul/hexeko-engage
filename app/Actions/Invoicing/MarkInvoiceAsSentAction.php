<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use LogicException;

class MarkInvoiceAsSentAction
{
    public function execute(Invoice $invoice): InvoiceDTO
    {
        if (! in_array($invoice->status, [InvoiceStatus::CONFIRMED, InvoiceStatus::SENT], true)) {
            throw new LogicException('Invoice must be confirmed before being sent.');
        }

        $invoice->update([
            'status' => InvoiceStatus::SENT,
            'sent_at' => Carbon::now(),
        ]);

        $refreshedInvoice = $invoice->fresh('items');
        if (! $refreshedInvoice instanceof Invoice) {
            throw new LogicException('Failed to refresh invoice after marking as sent.');
        }

        return InvoiceDTO::fromModel($refreshedInvoice);
    }
}
