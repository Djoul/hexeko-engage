<?php

namespace App\Http\Resources\Module;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Financer module resource with financer-specific attributes.
 *
 * @property string $id Module unique identifier
 * @property string $name Module name
 * @property string|null $description Module description
 * @property bool $is_core Whether this is a core module
 * @property bool $active Whether the module is active for the financer
 * @property bool $promoted Whether the module is promoted for the financer
 * @property int|null $price_per_beneficiary Price per beneficiary in cents (null for core modules, overrides division price)
 */
class FinancerModuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_core' => $this->is_core,
            'active' => $this->active ?? false,
            'promoted' => $this->promoted ?? false,
            'price_per_beneficiary' => $this->price_per_beneficiary ?? null,
        ];
    }
}
