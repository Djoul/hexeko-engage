<?php

namespace App\Integrations\Survey\Http\Resources\Me;

use App\Http\Resources\Segment\SegmentResource;
use App\Http\Resources\User\CreatorResource;
use App\Integrations\Survey\Enums\UserSurveyStatusEnum;
use App\Integrations\Survey\Http\Resources\QuestionResource;
use App\Integrations\Survey\Models\Survey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Survey */
class SurveyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            /**
             * The ID of the survey.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The title of the survey in the current app locale.
             *
             * @example "Survey 1"
             */
            'title' => $this->title ?? __('survey.untitled_survey'),
            /**
             * The title of the survey in all languages.
             *
             * @example {"en-GB": "Survey 1", "fr-FR": "Campagne 1"}
             */
            'title_raw' => $this->getTranslations('title'),
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
            /**
             * The welcome message of the survey in the current app locale.
             *
             * @example "Welcome to our survey."
             */
            'welcome_message' => $this->welcome_message,
            /**
             * The welcome message of the survey in all languages.
             *
             * @example {"en-GB": "Welcome to our survey.", "fr-FR": "Bienvenue dans notre campagne."}
             */
            'welcome_message_raw' => $this->getTranslations('welcome_message'),
            /**
             * The thank you message of the survey in the current app locale.
             *
             * @example "Thank you for participating."
             */
            'thank_you_message' => $this->thank_you_message,
            /**
             * The thank you message of the survey in all languages.
             *
             * @example {"en-GB": "Thank you for participating.", "fr-FR": "Merci de votre participation."}
             */
            'thank_you_message_raw' => $this->getTranslations('thank_you_message'),
            'status' => $this->getStatus(),
            'user_status' => $this->getUserStatus($user ?? null),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'financer_id' => $this->financer_id,
            'segment_id' => $this->segment_id,
            'segment' => new SegmentResource($this->whenLoaded('segment')),
            'questions_count' => $this->whenCounted('questions'),
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
            'users_count' => $this->users_count,
            'submissions_count' => $this->submissions_count,
            'user_progress_rate' => $this->when(
                $user !== null,
                function () use ($user): float {
                    assert($user !== null);

                    return round($this->progressRateFor($user));
                }
            ),
            'user_answers_count' => $this->when(
                $user !== null,
                function () use ($user): int {
                    assert($user !== null);

                    return $this->answersCountFor($user);
                }
            ),
            'user_submission_id' => $this->when(
                $user !== null,
                function () use ($user): string|null {
                    assert($user !== null);

                    return $this->submissionsFor($user)->latest()->first()?->id ?? null;
                }
            ),
            'is_favorite' => $this->whenLoaded('favorites', function () use ($user): bool {
                return $this->isFavoriteFor($user ?? null);
            }),
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'archived_at' => $this->archived_at,
        ];
    }

    private function getUserStatus($user = null): string
    {
        if ($user === null) {
            return UserSurveyStatusEnum::OPEN;
        }

        if ($this->isOngoingFor($user)) {
            return UserSurveyStatusEnum::ONGOING;
        }

        if ($this->isCompletedFor($user)) {
            return UserSurveyStatusEnum::COMPLETED;
        }

        return UserSurveyStatusEnum::OPEN;
    }
}
