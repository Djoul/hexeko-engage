<?php

namespace App\Http\Resources\Segment;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\User\CreatorResource;
use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Segment */
class SegmentResource extends JsonResource
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
             * The ID of the segment.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The name of the segment.
             *
             * @example "Segment 1"
             */
            'name' => $this->name,
            /**
             * The description of the segment.
             *
             * @example "Segment 1 description"
             */
            'description' => $this->description,
            /**
             * The filters of the segment.
             *
             * @example {"age": {"min": 18, "max": 30}, "gender": "male"}
             */
            'filters' => $this->filters,
            'financer_id' => $this->financer_id,
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            'users_count' => $this->whenCounted('users'),
            'computed_users_count' => $this->computed_users_count,
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
