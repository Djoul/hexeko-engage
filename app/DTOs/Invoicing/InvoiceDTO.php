<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Carbon;

class InvoiceDTO
{
    /**
     * @param  array<string, mixed>  $recipient
     * @param  array<string, string|null>  $dates
     * @param  array<int, InvoiceItemDTO>  $items
     */
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceNumber,
        public readonly string $status,
        public readonly array $recipient,
        public readonly InvoiceAmountsDTO $amounts,
        public readonly array $dates,
        public readonly array $items,
    ) {}

    public static function fromModel(Invoice $invoice): self
    {
        $invoice->loadMissing('items');

        $amounts = InvoiceAmountsDTO::fromInvoice($invoice);
        $currency = $invoice->currency;

        $items = $invoice->items->map(
            static fn (InvoiceItem $item): InvoiceItemDTO => InvoiceItemDTO::fromModel($item, prorata: null, currency: $currency)
        )->all();

        return new self(
            id: $invoice->id,
            invoiceNumber: $invoice->invoice_number,
            status: $invoice->status,
            recipient: [
                'type' => $invoice->recipient_type,
                'id' => $invoice->recipient_id,
            ],
            amounts: $amounts,
            dates: [
                'billing_period_start' => self::formatDate($invoice->billing_period_start),
                'billing_period_end' => self::formatDate($invoice->billing_period_end),
                'confirmed_at' => self::formatDateTime($invoice->confirmed_at),
                'sent_at' => self::formatDateTime($invoice->sent_at),
                'paid_at' => self::formatDateTime($invoice->paid_at),
                'due_date' => self::formatDate($invoice->due_date),
            ],
            items: $items,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'status' => $this->status,
            'recipient' => $this->recipient,
            'amounts' => $this->amounts->toArray(),
            'dates' => $this->dates,
            'items' => array_map(
                static fn (InvoiceItemDTO $item): array => $item->toArray(),
                $this->items,
            ),
        ];
    }

    private static function formatDate(?Carbon $date): ?string
    {
        return $date?->toDateString();
    }

    private static function formatDateTime(?Carbon $date): ?string
    {
        return $date?->toDateTimeString();
    }
}
