<?php

namespace App\Http\Resources\Division;

use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\DivisionStatus;
use App\Enums\Languages;
use App\Enums\TimeZones;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

/** @see \App\Models\Division */
class DivisionResourceCollection extends ResourceCollection
{
    /**
     * @return array{data: Collection, meta: array{total: int, countries: array<int, array{label: string, value: int|string}>, currencies: array<int, array{label: string, value: int|string}>, languages: array<int, array{label: string, value: int|string}>, timezones: array<string, string>, statuses: array<int, array{label: string, value: int|string}>}}
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'countries' => Countries::asSelectObject(),
                'currencies' => Currencies::asSelectObject(),
                'languages' => Languages::asSelectObjectFromSettings(),
                'timezones' => TimeZones::allWithLabels(),
                'statuses' => DivisionStatus::asSelectObject(),
            ],
        ];
    }
}
