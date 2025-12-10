<?php

declare(strict_types=1);

namespace App\Http\Resources\Gender;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $label
 * @property string $value
 */
class GenderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->value,
            'name' => $this->label,
        ];
    }
}
