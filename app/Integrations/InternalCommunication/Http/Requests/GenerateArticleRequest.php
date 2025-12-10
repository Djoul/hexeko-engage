<?php

namespace App\Integrations\InternalCommunication\Http\Requests;

use App\Models\Financer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'language' => [
                'required',
                'string',
                function ($attribute, $value, $fail): void {
                    $financerId = $this->input('financer_id');
                    if (! $financerId) {
                        $fail('The financer_id is required to validate language.');

                        return;
                    }

                    $financer = Financer::find($financerId);
                    if (! $financer) {
                        $fail('The selected financer does not exist.');

                        return;
                    }

                    $availableLanguages = $financer->available_languages ?? [];
                    if (! in_array($value, $availableLanguages)) {
                        $fail('The selected language is not available for this financer. Available languages: '.implode(', ', $availableLanguages));
                    }
                },
            ],
            'title' => ['sometimes', 'string', 'max:255'],
            'prompt' => ['required', 'string'],
            'prompt_system' => ['sometimes', 'string'],
            'selected_text' => ['sometimes', 'nullable', 'string'],
            'content' => ['sometimes', 'nullable', 'array'], // Current editor content (TipTap JSON)
            'financer_id' => [
                'required',
                'string',
                'exists:financers,id',
                Rule::in(authorizationContext()->financerIds()),
            ],
            'segment_id' => ['sometimes', 'nullable', 'uuid', 'exists:segments,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'language.required' => 'The language of the article is required.',
            'language.string' => 'The language must be a string.',
            'title.string' => 'The title must be a string.',
            'title.max' => 'The title may not be greater than 255 characters.',
            'prompt.required' => 'The prompt to generate the article is required.',
            'prompt.string' => 'The prompt must be a string.',
            'prompt_system.string' => 'The system message must be a string.',
            'selected_text.string' => 'The selected text must be a string.',
            'financer_id.required' => 'The financer ID is required.',
            'financer_id.string' => 'The financer ID must be a string.',
            'financer_id.exists' => 'The selected financer does not exist.',
            'financer_id.in' => 'You do not have access to this financer.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'language' => 'language of the article',
            'title' => 'article title',
            'prompt' => 'generation prompt',
            'prompt_system' => 'system message',
            'selected_text' => 'selected text',
            'financer_id' => 'financer',
        ];
    }
}
