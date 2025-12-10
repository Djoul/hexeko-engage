<?php

namespace App\Http\Resources\Financer;

use App\Models\Financer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Financer */
class FinancerWithPivotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $financerData = (new FinancerResource($this->resource))->toArray($request);
        $financerData['pivot'] = [];
        if ($this->pivot) {
            $pivotData = [
                'active' => $this->pivot->active,
                'language' => $this->pivot->language ?? null,
                'sirh_id' => $this->pivot->sirh_id,
                'role' => $this->pivot->role,
                'from' => $this->pivot->from?->toISOString(),
                'to' => $this->pivot->to?->toISOString(),
                'created_at' => $this->pivot->created_at?->toISOString(),
                'updated_at' => $this->pivot->updated_at?->toISOString(),
            ];

            $financerData['pivot'] = $pivotData;
        }

        return $financerData;
    }
}
