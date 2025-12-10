<?php

namespace App\Http\Requests\Segment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid|exists:financers,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:255',
            'filters' => 'sometimes|array',
        ];
    }
}
