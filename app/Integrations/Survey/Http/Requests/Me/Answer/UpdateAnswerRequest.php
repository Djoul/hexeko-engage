<?php

namespace App\Integrations\Survey\Http\Requests\Me\Answer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'answer' => 'required|array',
        ];
    }
}
