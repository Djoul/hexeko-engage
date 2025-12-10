<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use App\Integrations\Vouchers\Amilon\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Merchant */
class MerchantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->getAttributes()['category'] ?? $this->category, // Use temp category or fallback to accessor
            'categories' => $this->categories->pluck('name', 'id')->toArray(),
            'country' => $this->country,
            'merchant_id' => $this->merchant_id,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'average_discount' => $this->average_discount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
