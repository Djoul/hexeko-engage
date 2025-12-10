<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_order_id' => $this->external_order_id,
            'user_id' => $this->user_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'amount' => $this->amount,
            'currency' => $this->product ? $this->product->currency : 'EUR',
            'status' => $this->status,
            // 'payment_status' => $this->payment_status, // Column removed
            'payment_id' => $this->payment_id,
            'voucher_code' => $this->when($this->voucher_code !== null, $this->voucher_code),
            // 'voucher_pin' => $this->when($this->voucher_pin, $this->voucher_pin), // Column does not exist
            'recovery_attempts' => $this->recovery_attempts,
            'last_error' => $this->when($this->last_error !== null, $this->last_error),
            'can_retry' => $this->status === 'failed' && ($this->recovery_attempts ?? 0) < 5,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
