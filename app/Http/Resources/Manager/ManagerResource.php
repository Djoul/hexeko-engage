<?php

namespace App\Http\Resources\Manager;

use App\Models\Manager;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Manager */
class ManagerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The ID of the manager.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The first name of the manager.
             *
             * @example "John"
             */
            'first_name' => $this->first_name,
            /**
             * The last name of the manager.
             *
             * @example "Doe"
             */
            'last_name' => $this->last_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
