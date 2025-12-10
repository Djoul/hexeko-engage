<?php

namespace App\Integrations\Survey\Http\Resources;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\User\CreatorResource;
use App\Integrations\Survey\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Questionnaire */
class QuestionnaireResource extends JsonResource
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
             * The ID of the questionnaire.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The name of the questionnaire in the current app locale.
             *
             * @example "Questionnaire 1"
             */
            'name' => $this->name ?? __('survey.unnamed_questionnaire'),
            /**
             * The name of the questionnaire in all languages.
             *
             * @example {"en-GB": "Questionnaire 1", "fr-FR": "Questionnaire 1"}
             */
            'name_raw' => $this->getTranslations('name'),
            /**
             * The description of the questionnaire in the current app locale.
             *
             * @example "Questionnaire 1 description"
             */
            'description' => $this->description,
            /**
             * The description of the questionnaire in all languages.
             *
             * @example {"en-GB": "Questionnaire 1 description", "fr-FR": "Description du Questionnaire 1"}
             */
            'description_raw' => $this->getTranslations('description'),
            'financer_id' => $this->financer_id,
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            'status' => $this->status,
            'settings' => $this->settings,
            'is_default' => (bool) $this->is_default,
            'questions_count' => $this->whenCounted('questions'),
            'questions' => $this->whenLoaded('questions', function () {
                return QuestionResource::collection($this->questions->sortBy('position')->values());
            }),
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
