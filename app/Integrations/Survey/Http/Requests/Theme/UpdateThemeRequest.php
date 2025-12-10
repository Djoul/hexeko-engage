<?php

namespace App\Integrations\Survey\Http\Requests\Theme;

use App\Helpers\LanguageHelper;
use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    /** @return array<string, string> */
    public function rules(): array
    {
        $rules = [
            'financer_id' => 'required|exists:financers,id',
            'name' => 'required|array',
            'description' => 'required|array',
            'position' => 'nullable|integer',
            /**
             * The IDs of the questions.
             *
             * @var array
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000", "123e4567-e89b-12d3-a456-426614174001"]
             */
            'questions' => 'sometimes|array',
        ];

        $languages = LanguageHelper::getLanguages();
        foreach ($languages as $language) {
            $rules["name.$language"] = 'required|string|max:255';
            $rules["description.$language"] = 'required|string|max:255';
        }

        return $rules;
    }
}
