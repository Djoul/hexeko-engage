<?php

namespace App\Integrations\Survey\Http\Resources;

use App\Integrations\Survey\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin QuestionOption */
class QuestionOptionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The ID of the question option.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The text of the question option in the current app locale.
             *
             * @example "Option 1"
             */
            'text' => $this->text,
            /**
             * The text of the question option in all languages.
             *
             * @example {"en-GB": "Option 1", "fr-FR": "Option 1", "nl-BE": "Optie 1"}
             */
            'text_raw' => $this->getTranslations('text'),
            /**
             * The display order of the question option.
             *
             * @example 1
             */
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
