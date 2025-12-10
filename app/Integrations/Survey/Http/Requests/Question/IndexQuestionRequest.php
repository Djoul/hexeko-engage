<?php

namespace App\Integrations\Survey\Http\Requests\Question;

use App\Integrations\Survey\Enums\QuestionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class IndexQuestionRequest extends FormRequest
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
            'theme_id' => 'sometimes|uuid',
            'financer_id' => 'sometimes|uuid',
            'type' => 'sometimes|string |in:'.implode(',', QuestionTypeEnum::getValues()),
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
