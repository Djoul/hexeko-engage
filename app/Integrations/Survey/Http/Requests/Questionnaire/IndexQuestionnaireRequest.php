<?php

namespace App\Integrations\Survey\Http\Requests\Questionnaire;

use App\Integrations\Survey\Enums\QuestionnaireStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class IndexQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'financer_id' => 'sometimes|uuid',
            'status' => 'sometimes|string|in:'.implode(',', QuestionnaireStatusEnum::getValues()),
            'is_default' => 'sometimes|string|in:true,false,0,1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'order-by' => 'sometimes|string',
            'order-by-desc' => 'sometimes|string',
            'created_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date',
            'deleted_at' => 'sometimes|date',
        ];
    }
}
