<?php

namespace App\Integrations\Survey\Http\Requests\Questionnaire;

use Illuminate\Foundation\Http\FormRequest;

class UnlinkQuestionnaireQuestionRequest extends FormRequest
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
             * Array of question IDs. Array format: ["id1", "id2"].
             */
            'questions' => 'required|array',
        ];
    }
}
