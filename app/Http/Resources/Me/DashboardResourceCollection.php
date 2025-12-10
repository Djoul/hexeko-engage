<?php

namespace App\Http\Resources\Me;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DashboardResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<int|string, mixed> $result */
        $result = parent::toArray($request);

        return $result;
    }
}
