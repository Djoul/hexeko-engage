<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'push_enabled' => ['nullable', 'boolean'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.*' => ['boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'language' => ['nullable', 'string', 'regex:/^[a-z]{2}-[A-Z]{2}$/'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'language.regex' => 'The language must be in format: en-US, fr-FR, etc.',
            'timezone.timezone' => 'The timezone must be a valid timezone identifier.',
            'notification_preferences.*.boolean' => 'Each notification preference must be true or false.',
        ];
    }
}
