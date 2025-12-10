<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MobileVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        return [
            'financer_id' => 'nullable|uuid|exists:financers,id',
            'user_id' => 'nullable|uuid|exists:users,id',
            'platform' => 'required|string|in:ios,android',
            'version' => 'required|string',
        ];
    }
}
