<?php

namespace App\Integrations\Survey\Http\Requests\Me\Submission;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubmissionRequest extends FormRequest
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
        ];
    }
}
