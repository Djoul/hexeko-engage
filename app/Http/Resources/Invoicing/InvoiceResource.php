<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoicing;

use App\DTOs\Invoicing\InvoiceAmountsDTO;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice API Resource.
 *
 * Transforms an Invoice model into a structured API response including
 * recipient and issuer details, monetary amounts with VAT breakdown,
 * billing periods, status tracking dates, and associated items.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $amountsResource = new InvoiceAmountsResource(InvoiceAmountsDTO::fromInvoice($this->resource));

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'invoice_type' => $this->invoice_type,
            'status' => $this->status,
            'recipient' => [
                'id' => $this->recipient_id,
                'type' => $this->recipient_type,
                'name' => $this->whenLoaded('recipient', fn () => $this->recipient?->name),
            ],
            'issuer' => [
                'id' => $this->issuer_id,
                'type' => $this->issuer_type,
            ],
            'amounts' => $amountsResource->toArray($request),
            'currency' => $this->currency,
            'billing_period' => [
                'start' => $this->billing_period_start?->toDateString(),
                'end' => $this->billing_period_end?->toDateString(),
            ],
            'dates' => [
                'confirmed_at' => $this->confirmed_at?->toISOString(),
                'sent_at' => $this->sent_at?->toISOString(),
                'paid_at' => $this->paid_at?->toISOString(),
                'due_date' => $this->due_date?->toDateString(),
            ],
            'items_count' => $this->whenLoaded('items', fn () => $this->items->count()),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'metadata' => $this->metadata ?? [],
            'pdf_url' => null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
