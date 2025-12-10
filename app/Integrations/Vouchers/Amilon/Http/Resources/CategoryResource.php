<?php

namespace App\Integrations\Vouchers\Amilon\Http\Resources;

use App\Integrations\Vouchers\Amilon\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Handle both array format from service and Category model
        if (is_array($this->resource)) {
            return [
                'CategoryId' => $this->resource['CategoryId'] ?? null,
                'CategoryName' => $this->resource['CategoryName'] ?? null,
            ];
        }

        /** @var Category $category */
        $category = $this->resource;

        return [
            'CategoryId' => $category->id,
            'CategoryName' => $category->name,
        ];
    }
}
