<?php

namespace App\Http\Requests\Manager;

use App\Helpers\LanguageHelper;
use Illuminate\Foundation\Http\FormRequest;

class UpdateManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|array<string|object>> */
    public function rules(): array
    {
        $rules = [
            'financer_id' => 'required|uuid',
            'name' => 'required|array',
        ];

        $languages = LanguageHelper::getLanguages();
        foreach ($languages as $language) {
            $rules["name.$language"] = 'required|string|max:255';
        }

        return $rules;
    }
}
