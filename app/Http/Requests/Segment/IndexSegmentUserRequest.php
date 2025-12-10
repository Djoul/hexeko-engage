<?php

namespace App\Http\Requests\Segment;

use Illuminate\Foundation\Http\FormRequest;

class IndexSegmentUserRequest extends FormRequest
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
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ];
    }
}
