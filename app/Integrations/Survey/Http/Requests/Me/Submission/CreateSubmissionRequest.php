<?php

namespace App\Integrations\Survey\Http\Requests\Me\Submission;

use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;

class CreateSubmissionRequest extends FormRequest
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
            'survey_id' => ['required', new BelongsToCurrentFinancer('int_survey_surveys')],
        ];
    }
}
