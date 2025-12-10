<?php

namespace App\Integrations\Survey\Http\Requests\Survey;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var string|null $status */
        // Fallback to make compatible with old requests
        $status = $this->input('status');
        if (in_array($status, ['new', 'published', 'scheduled', 'active', 'closed'], true)) {
            $this->merge(['status' => SurveyStatusEnum::PUBLISHED]);
        }
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        // Get status after prepareForValidation() transformation
        // prepareForValidation() runs before rules(), so status is already transformed
        $status = $this->input('status');
        $isPublished = $status === SurveyStatusEnum::PUBLISHED;

        $startsAtRules = ['sometimes', 'nullable', 'date'];
        $endsAtRules = ['sometimes', 'nullable', 'date'];

        // If status is published, starts_at and ends_at are required
        if ($isPublished) {
            $startsAtRules = ['required', 'date'];
            $endsAtRules = ['required', 'date'];
        }

        // Only validate ends_at after starts_at if starts_at is provided and not null
        if ($this->filled('starts_at') && $this->input('starts_at') !== null) {
            $endsAtRules[] = 'after:starts_at';
        }

        $rules = [
            'financer_id' => 'required|uuid',
            'segment_id' => 'sometimes|nullable|exists:segments,id',
            'title' => 'sometimes|required|array',
            'description' => 'sometimes|required|array',
            'welcome_message' => 'sometimes|array',
            'thank_you_message' => 'sometimes|array',
            'settings' => 'sometimes|array',
            'status' => 'sometimes|string|in:'.implode(',', SurveyStatusEnum::getStaticValues()),
            'starts_at' => $startsAtRules,
            'ends_at' => $endsAtRules,
            'questions' => ['sometimes', 'array', new BelongsToCurrentFinancer('int_survey_questions')],
            'refresh_users' => 'sometimes|boolean',
        ];

        $languages = LanguageHelper::getLanguages();
        foreach ($languages as $language) {
            $rules["title.$language"] = 'sometimes|required|string|max:255';
            $rules["description.$language"] = 'sometimes|required|string';
            $rules["welcome_message.$language"] = 'sometimes|string';
            $rules["thank_you_message.$language"] = 'sometimes|string';
        }

        return $rules;
    }
}
