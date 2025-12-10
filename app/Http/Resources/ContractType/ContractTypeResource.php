<?php

namespace App\Http\Resources\ContractType;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\User\CreatorResource;
use App\Models\ContractType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContractType */
class ContractTypeResource extends JsonResource
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
             * The ID of the contract type.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The title of the contract type.
             *
             * @example "Permanent Contract"
             */
            'name' => $this->name,
            'financer_id' => $this->financer_id,
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
