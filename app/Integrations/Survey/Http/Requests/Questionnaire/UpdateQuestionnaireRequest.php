<?php

namespace App\Integrations\Survey\Http\Requests\Questionnaire;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Enums\QuestionnaireStatusEnum;
use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        $rules = [
            'financer_id' => 'required|exists:financers,id',
            'name' => 'required|array',
            'description' => 'required|array',
            'instructions' => 'sometimes|array',
            'settings' => 'sometimes|array',
            'type' => 'sometimes|string|in:'.implode(',', QuestionnaireTypeEnum::getValues()),
            'questions' => ['sometimes', 'array', new BelongsToCurrentFinancer('int_survey_questions')],
            'status' => 'sometimes|string|in:'.implode(',', QuestionnaireStatusEnum::getValues()),
        ];

        $languages = LanguageHelper::getLanguages();
        foreach ($languages as $language) {
            $rules["name.$language"] = 'required|string|max:255';
            $rules["description.$language"] = 'required|string|max:255';
            $rules["instructions.$language"] = 'sometimes|string|max:255';
        }

        return $rules;
    }
}
