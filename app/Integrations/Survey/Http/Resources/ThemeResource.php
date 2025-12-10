<?php

namespace App\Integrations\Survey\Http\Resources;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\User\CreatorResource;
use App\Integrations\Survey\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Theme */
class ThemeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            /**
             * The ID of the theme.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The name of the theme in the current app locale.
             *
             * @example "Theme 1"
             */
            'name' => $this->name,
            /**
             * The name of the theme in all languages.
             *
             * @example {"en-GB": "Theme 1", "fr-FR": "Thème 1"}
             */
            'name_raw' => $this->getTranslations('name'),
            /**
             * The description of the theme in the current app locale.
             *
             * @example "Theme 1 description"
             */
            'description' => $this->description,
            /**
             * The description of the theme in all languages.
             *
             * @example {"en-GB": "Theme 1 description", "fr-FR": "Description du Thème 1"}
             */
            'description_raw' => $this->getTranslations('description'),
            'financer_id' => $this->financer_id,
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            'is_default' => $this->is_default,
            'position' => $this->position,
            'question_count' => $this->whenCounted('defaultQuestions'),
            'questions' => $this->whenLoaded('defaultQuestions', function () {
                return QuestionResource::collection($this->defaultQuestions->sortBy('text')->values());
            }),
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
