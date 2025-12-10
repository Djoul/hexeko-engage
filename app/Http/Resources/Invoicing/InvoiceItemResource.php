<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoicing;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invoice Item API Resource.
 *
 * Transforms an InvoiceItem model into a structured API response including
 * item type and module information, pricing details with VAT calculations,
 * quantity and beneficiary count, and optional prorata breakdown.
 *
 * @mixin InvoiceItem
 */
class InvoiceItemResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'module_id' => $this->module_id,
            'label' => $this->label,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'beneficiaries_count' => $this->beneficiaries_count,
            'amounts' => [
                'unit_price_htva' => $this->unit_price_htva,
                'subtotal_htva' => $this->subtotal_htva,
                'vat_rate' => $this->vat_rate,
                'vat_amount' => $this->vat_amount,
                'total_ttc' => $this->total_ttc,
            ],
            'prorata' => [
                'percentage' => $this->prorata_percentage,
                'days' => $this->prorata_days,
                'total_days' => $this->total_days,
            ],
            'metadata' => $this->metadata ?? [],
        ];
    }
}
