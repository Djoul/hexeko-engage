<?php

namespace App\Integrations\Survey\Http\Requests\Questionnaire;

use Illuminate\Foundation\Http\FormRequest;

class IndexQuestionnaireQuestionRequest extends FormRequest
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
            'financer_id' => 'required|uuid',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'order-by' => 'sometimes|string',
            'order-by-desc' => 'sometimes|string',
        ];
    }
}
