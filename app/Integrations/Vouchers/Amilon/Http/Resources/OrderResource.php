<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use App\Http\Resources\User\UserResource;
use App\Integrations\Vouchers\Amilon\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'external_order_id' => $this->external_order_id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'price_paid' => $this->price_paid,
            'voucher_url' => $this->voucher_url,
            'payment_id' => $this->payment_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'order_date' => $this->order_date,
            'order_status' => $this->order_status,
            'gross_amount' => $this->gross_amount,
            'net_amount' => $this->net_amount,
            'total_requested_codes' => $this->total_requested_codes,

            // New fields for purchase history
            'voucher_code' => $this->voucher_code,
            'metadata' => $this->metadata,
            'total_amount' => $this->total_amount,
            'payment_method' => $this->payment_method,

            // Recovery fields
            'recovery_attempts' => $this->recovery_attempts,
            'can_retry' => $this->status === 'failed' && ($this->recovery_attempts ?? 0) < 5,

            'merchant_id' => $this->merchant_id,
            'user_id' => $this->user_id,

            // Relations - include as null when not loaded
            'user' => $this->relationLoaded('user') ? new UserResource($this->user) : null,
            'merchant' => $this->relationLoaded('merchant') ? new MerchantResource($this->merchant) : null,
            'product' => $this->relationLoaded('product') ? new ProductResource($this->product) : null,
            'items' => $this->relationLoaded('items') ? OrderItemResource::collection($this->items) : null,
        ];
    }
}
