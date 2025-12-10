<?php

namespace App\Http\Resources\Translations;

use App\Enums\Languages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class TranslationKeyResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array{data: Collection<int, mixed>, meta: array{total: int, languages: array<int, array<string, mixed>>}}
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'languages' => Languages::asSelectObjectFromSettings(),
            ],
        ];
    }
}
