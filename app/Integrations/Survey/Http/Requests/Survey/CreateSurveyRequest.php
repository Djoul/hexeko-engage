<?php

namespace App\Integrations\Survey\Http\Requests\Survey;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;

class CreateSurveyRequest extends FormRequest
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
        $rules = [
            'financer_id' => 'required|uuid',
            'segment_id' => 'sometimes|nullable|exists:segments,id',
            'title' => 'required|array',
            'description' => 'required|array',
            'welcome_message' => 'sometimes|array',
            'thank_you_message' => 'sometimes|array',
            'settings' => 'sometimes|array',
            'status' => 'required|string|in:'.implode(',', SurveyStatusEnum::getStaticValues()),
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'questions' => ['sometimes', 'array', new BelongsToCurrentFinancer('int_survey_questions')],
        ];

        $languages = LanguageHelper::getLanguages();
        foreach ($languages as $language) {
            $rules["title.$language"] = 'required|string|max:255';
            $rules["description.$language"] = 'required|string';
            $rules["welcome_message.$language"] = 'sometimes|string';
            $rules["thank_you_message.$language"] = 'sometimes|string';
        }

        return $rules;
    }
}
