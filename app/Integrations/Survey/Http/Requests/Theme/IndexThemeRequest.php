<?php

namespace App\Integrations\Survey\Http\Requests\Theme;

use Illuminate\Foundation\Http\FormRequest;

class IndexThemeRequest extends FormRequest
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
            'name' => 'sometimes|string',
            'description' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
            'order-by' => 'sometimes|string',
            'order-by-desc' => 'sometimes|string',
            'is_default' => 'sometimes|string|in:true,false,0,1',
            'position' => 'sometimes|integer',
            'created_at' => 'sometimes|date',
            'updated_at' => 'sometimes|date',
            'deleted_at' => 'sometimes|date',
        ];
    }
}
