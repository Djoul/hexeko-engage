<?php

namespace App\Http\Resources;

use App\Integrations\Vouchers\Amilon\Http\Resources\OrderResource;
use App\Integrations\Vouchers\Amilon\Http\Resources\ProductResource;
use App\Integrations\Vouchers\Amilon\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'vouchers' => $this->vouchers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'order' => new OrderResource($this->whenLoaded('order')),
            'product' => new ProductResource($this->whenLoaded('product')),
        ];
    }
}
