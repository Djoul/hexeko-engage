<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceItem;

class InvoiceAmountsDTO
{
    public function __construct(
        public readonly int $subtotalHtva,
        public readonly int $vatAmount,
        public readonly int $totalTtc,
        public readonly string $currency,
    ) {}

    public static function fromInvoice(Invoice $invoice): self
    {
        return new self(
            subtotalHtva: $invoice->subtotal_htva,
            vatAmount: $invoice->vat_amount,
            totalTtc: $invoice->total_ttc,
            currency: $invoice->currency,
        );
    }

    public static function fromItem(InvoiceItem $item, string $currency): self
    {
        return new self(
            subtotalHtva: $item->subtotal_htva,
            vatAmount: $item->vat_amount ?? 0,
            totalTtc: $item->total_ttc ?? ($item->subtotal_htva + ($item->vat_amount ?? 0)),
            currency: $currency,
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
            'subtotal_htva' => $this->subtotalHtva,
            'vat_amount' => $this->vatAmount,
            'total_ttc' => $this->totalTtc,
            'currency' => $this->currency,
        ];
    }
}
