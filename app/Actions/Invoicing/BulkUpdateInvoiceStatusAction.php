<?php

declare(strict_types=1);

namespace App\Actions\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use InvalidArgumentException;
use Throwable;

class BulkUpdateInvoiceStatusAction
{
    public function __construct(
        private readonly ConfirmInvoiceAction $confirmInvoiceAction,
        private readonly MarkInvoiceAsSentAction $markInvoiceAsSentAction,
        private readonly MarkInvoiceAsPaidAction $markInvoiceAsPaidAction,
    ) {}

    /**
     * @param  array{invoice_ids: array<int, string>, status: string}  $payload
     * @return array{updated: int, failed: int, errors: array<int, array<string, string>>}
     */
    public function execute(array $payload): array
    {
        $invoiceIds = $payload['invoice_ids'] ?? [];
        $status = $payload['status'] ?? null;

        if (! is_array($invoiceIds) || $status === null) {
            throw new InvalidArgumentException('Invalid payload.');
        }

        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);

            if ($invoice === null) {
                $failed++;
                $errors[] = ['id' => (string) $invoiceId, 'message' => 'Invoice not found'];

                continue;
            }

            try {
                $this->updateInvoiceStatus($invoice, $status);
                $updated++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = ['id' => $invoice->id, 'message' => $e->getMessage()];
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function updateInvoiceStatus(Invoice $invoice, string $status): void
    {
        match ($status) {
            InvoiceStatus::CONFIRMED => $this->confirmInvoiceAction->execute($invoice),
            InvoiceStatus::SENT => $this->markInvoiceAsSentAction->execute($invoice),
            InvoiceStatus::PAID => $this->markInvoiceAsPaidAction->execute($invoice, $invoice->total_ttc),
            InvoiceStatus::CANCELLED => $invoice->update(['status' => InvoiceStatus::CANCELLED]),
            default => throw new InvalidArgumentException("Unsupported status [{$status}]."),
        };
    }
}
