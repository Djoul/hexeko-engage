<?php

namespace App\Integrations\Survey\Http\Requests\Questionnaire;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQuestionnaireQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|string',
            'questions' => 'required|array',
            'questions.*.id' => 'required|string',
            'questions.*.position' => 'required|integer|min:0',
        ];
    }
}
