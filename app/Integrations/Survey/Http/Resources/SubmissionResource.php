<?php

namespace App\Integrations\Survey\Http\Resources;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\User\CreatorResource;
use App\Http\Resources\User\UserResource;
use App\Integrations\Survey\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Submission */
class SubmissionResource extends JsonResource
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
             * The ID of the submission.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,
            /**
             * The ID of the financer.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financer_id' => $this->financer_id,
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            /**
             * The ID of the user.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            /**
             * The ID of the survey.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'survey_id' => $this->survey_id,
            'survey' => new SurveyResource($this->whenLoaded('survey')),
            /**
             * The started at date of the submission.
             *
             * @example "2024-01-01 00:00:00"
             */
            'started_at' => $this->started_at,
            /**
             * The completed at date of the submission.
             *
             * @example "2024-01-01 00:00:00"
             */
            'completed_at' => $this->completed_at,
            'answers_count' => $this->whenCounted('answers'),
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
            'created_by' => new CreatorResource($this->whenLoaded('creator')),
            'updated_by' => new CreatorResource($this->whenLoaded('updater')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
