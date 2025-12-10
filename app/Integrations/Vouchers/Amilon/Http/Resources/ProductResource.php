<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * NOTE: All monetary amounts (price, net_price, discount) are returned in CENTS.
     * Frontend should handle these as cents (e.g., 1000 = €10.00).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'merchant_id' => $this->merchant_id,
            'product_code' => $this->product_code,
            'price' => $this->price,                    // Price in cents
            'net_price' => $this->net_price ?? 0,       // Net price in cents
            'discount' => $this->discount ?? 0,         // Discount in cents
            'currency' => $this->currency,
            'country' => $this->country,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'merchant' => new MerchantResource($this->whenLoaded('merchant')),
            'category_model' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
