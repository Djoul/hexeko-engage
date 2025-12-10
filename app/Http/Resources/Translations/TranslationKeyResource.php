<?php

namespace App\Http\Resources\Translations;

use App\Models\TranslationValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $key
 * @property string|null $group
 * @property Collection<int, TranslationValue> $values
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TranslationKeyResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'group' => $this->group,
            'values' => $this->whenLoaded('values', function () {
                return $this->values->mapWithKeys(function (mixed $val, $key): array {
                    /** @var TranslationValue $val */
                    return [(string) $val->locale => $val->value];
                });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
