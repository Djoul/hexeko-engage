<?php

namespace App\Integrations\Survey\Http\Requests\Question;

use App\Helpers\LanguageHelper;
use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        $rules = [
            'financer_id' => 'required|uuid',
            'questionnaire_id' => ['sometimes', 'nullable', new BelongsToCurrentFinancer('int_survey_questionnaires')],
            'survey_id' => ['sometimes', 'nullable', new BelongsToCurrentFinancer('int_survey_surveys')],
            'text' => 'required|array',
            'help_text' => 'sometimes|array',
            'options' => 'sometimes|array',
            'type' => 'required|string|in:'.implode(',', QuestionTypeEnum::getValues()),
            'theme_id' => ['required', new BelongsToCurrentFinancer('int_survey_themes')],
            'metadata' => 'sometimes|array',
        ];

        $languages = LanguageHelper::getLanguages();
        foreach ($languages as $language) {
            $rules["text.$language"] = 'required|string|max:255';
            $rules["help_text.$language"] = 'sometimes|string|max:255';
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $questionnaireId = $this->input('questionnaire_id');
            $surveyId = $this->input('survey_id');

            if ($questionnaireId !== null && $surveyId !== null) {
                $validator->errors()->add(
                    'questionnaire_id',
                    'You cannot provide both questionnaire_id and survey_id at the same time.'
                );
            }
        });
    }
}
