<?php

namespace App\Integrations\Survey\Http\Requests\Survey;

use Illuminate\Foundation\Http\FormRequest;

class LinkSurveyQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            /**
             * Array of questions with required id and optional position.
             * Format: [{"id": "uuid", "position": 2}, {"id": "uuid"}]
             */
            'questions' => 'nullable|array',
            'questions.*.id' => 'required|string',
            'questions.*.position' => 'nullable|integer|min:0',
            'questionnaires' => 'nullable|array',
        ];
    }
}
