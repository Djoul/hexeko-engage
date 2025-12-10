<?php

namespace App\Http\Resources\Financer;

use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\FinancerStatus;
use App\Enums\Languages;
use App\Enums\TimeZones;
use App\Http\Resources\Division\DivisionResource;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** @see \App\Models\Financer */
class FinancerResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource into an array.

     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request
    ): array {
        $divisions = Division::get();

        return [
            'data' => $this->collection,
            'meta' => [
                'countries' => Countries::asSelectObject(),
                'currencies' => Currencies::asSelectObject(),
                'languages' => Languages::asSelectObjectFromSettings(),
                'timezones' => TimeZones::allWithLabels(),
                'statuses' => FinancerStatus::asSelectObject(),
                'divisions' => DivisionResource::collection($divisions),
                'divisions_array' => $divisions->map(fn ($division): array => [
                    'value' => $division->id,
                    'label' => $division->name,
                ]),
                'users' => User::get()->map(fn ($user): array => [
                    'value' => $user->id,
                    'label' => $user->full_name,
                ]),
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $base = parent::with($request);

        // If pagination metadata was added via additional(), merge it into meta
        if (isset($base['meta'])) {
            $base['meta'] = array_merge(
                $this->toArray($request)['meta'] ?? [],
                $base['meta']
            );
        }

        return $base;
    }
}
