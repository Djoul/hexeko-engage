<?php

namespace App\Http\Requests\Manager;

use Illuminate\Foundation\Http\FormRequest;

class IndexManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid',
            'name' => 'sometimes|string',
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
