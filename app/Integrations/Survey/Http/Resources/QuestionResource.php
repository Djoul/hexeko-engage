<?php

namespace App\Integrations\Survey\Http\Resources;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\User\CreatorResource;
use App\Integrations\Survey\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Question */
class QuestionResource extends JsonResource
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
             * The ID of the question.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            'original_question_id' => $this->original_question_id,
            /**
             * The text of the question in the current app locale.
             *
             * @example "What is your name?"
             */
            'text' => $this->text,
            /**
             * The text of the question in all languages.
             *
             * @example {"en-GB": "What is your name?", "fr-FR": "Comment vous appelez-vous?"}
             */
            'text_raw' => $this->getTranslations('text'),
            /**
             * The help text of the question in the current app locale.
             *
             * @example "Please enter your name."
             */
            'help_text' => $this->help_text,
            /**
             * The help text of the question in all languages.
             *
             * @example {"en-GB": "Please enter your name.", "fr-FR": "Veuillez entrer votre nom."}
             */
            'help_text_raw' => $this->getTranslations('help_text'),
            'type' => $this->type,
            /**
             * The position of the question in the questionnaire/survey (from pivot table).
             *
             * @example 1
             */
            'position' => $this->when(isset($this->pivot), fn () => $this->pivot?->position ?? 0),
            'metadata' => $this->metadata,
            'theme_id' => $this->whenLoaded('theme', fn () => $this->theme->id ?? null),
            'theme' => new ThemeResource($this->whenLoaded('theme')),
            'financer_id' => $this->financer_id,
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            'options' => QuestionOptionResource::collection($this->whenLoaded('options')),
            'answers_count' => $this->whenCounted('answers'),
            'answers_metrics' => $this->whenLoaded('answers', fn () => $this->getAnswersMetrics()),
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
            'is_default' => (bool) $this->is_default,
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'archived_at' => $this->archived_at,
        ];
    }
}
