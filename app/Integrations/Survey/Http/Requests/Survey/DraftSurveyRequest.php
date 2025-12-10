<?php

namespace App\Integrations\Survey\Http\Requests\Survey;

use Illuminate\Foundation\Http\FormRequest;

class DraftSurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        return [
            'financer_id' => 'required|uuid',
        ];
    }
}
