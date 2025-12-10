<?php

namespace App\Integrations\Survey\Http\Requests\Theme;

use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            /**
             * The IDs of the questions.
             *
             * @var array
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000", "123e4567-e89b-12d3-a456-426614174001"]
             */
            'questions' => ['required', 'array', new BelongsToCurrentFinancer('int_survey_questions')],
        ];
    }
}
