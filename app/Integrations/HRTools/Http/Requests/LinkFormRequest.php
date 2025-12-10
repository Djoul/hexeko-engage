<?php

namespace App\Integrations\HRTools\Http\Requests;

use App\Enums\Languages;
use App\Models\Financer;
use App\Rules\LooseUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @group Modules/HRTools
 *
 * Data validation for link creation and updating.
 *
 * This request handles validation for link resources with multilingual attributes (name, description, url).
 * These translatable attributes are strongly coupled with the financer's available_languages.
 * Each translatable field must be provided for all languages available to the financer.
 */
class LinkFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The rules are dynamically generated based on the financer's available languages.
     * For each available language, validation rules are created for name, description, and url fields.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $availableLanguages = $this->getAvailableLanguages();
        $rules = [];

        // Add validation rules for each available language
        foreach ($availableLanguages as $lang) {
            /**
             * The name of the link in each language.
             *
             * This is a translatable field that must be provided for all languages available to the financer.
             * The keys of this array must match the language codes in the financer's available_languages.
             *
             *
             * @example {"fr-FR": "Guide RH", "en-GB": "HR Guide"}
             */
            $rules["name.$lang"] = ['required', 'string', 'max:255'];

            /**
             * The description of the link in each language.
             *
             * This is a translatable field that can be provided for all languages available to the financer.
             * The keys of this array must match the language codes in the financer's available_languages.
             *
             *
             * @example {"fr-FR": "Guide complet pour les ressources humaines", "en-GB": "Complete guide for human resources"}
             */
            $rules["description.$lang"] = ['nullable', 'string'];

            /**
             * The URL of the link in each language.
             *
             * This is a translatable field that must be provided for all languages available to the financer.
             * The keys of this array must match the language codes in the financer's available_languages.
             * Each URL must be a valid URL format (validated by LooseUrl rule).
             *
             *
             * @example {"fr-FR": "https://example.com/guide-fr", "en-GB": "https://example.com/guide-en"}
             */
            $rules["url.$lang"] = ['required', new LooseUrl];
        }

        /**
         * The name translations array.
         *
         * This must be an array containing translations for each language available to the financer.
         *
         *
         * @example {"fr-FR": "Guide RH", "en-GB": "HR Guide"}
         */
        $rules['name'] = ['required', 'array'];

        /**
         * The URL translations array.
         *
         * This must be an array containing URL translations for each language available to the financer.
         *
         *
         * @example {"fr-FR": "https://example.com/guide-fr", "en-GB": "https://example.com/guide-en"}
         */
        $rules['url'] = ['required', 'array'];

        /**
         * The description translations array.
         *
         * This can be an array containing description translations for each language available to the financer.
         *
         *
         * @example {"fr-FR": "Guide complet pour les ressources humaines", "en-GB": "Complete guide for human resources"}
         */
        $rules['description'] = ['nullable', 'array'];

        // Add validation rules to ensure required languages are present
        foreach ($availableLanguages as $lang) {
            /**
             * The name translated in a specific language (only required if in financer's available_languages).
             *
             *
             * @example "Guide RH"
             */
            $rules['name.'.$lang] = ['required', 'string', 'max:255'];
            /**
             * The specific url translated in a specific language (only required if in financer's available_languages).
             *
             *
             * @example "https://example.com/guide-fr"
             */
            $rules['url.'.$lang] = ['required', new LooseUrl];
        }

        // Add the rest of the rules
        $rules = array_merge($rules, [

            /**
             * The URL of the logo (alternative to file upload).
             *
             * @var string
             *
             * @example "https://example.com/logo.png"
             */
            'logo_url' => ['nullable', 'url'],

            /**
             * The API endpoint associated with the link.
             *
             * @var string
             *
             * @example "/api/v1/guide"
             */
            'api_endpoint' => ['nullable', 'string'],

            /**
             * The frontend endpoint associated with the link.
             *
             * @var string
             *
             * @example "/guide"
             */
            'front_endpoint' => ['nullable', 'string'],
            /**
             * The logo file as base64 encoded string.
             *
             * @var string
             */
            'logo' => ['sometimes', 'nullable', 'string'],

            /**
             * The UUID of the financer associated with the link.
             *
             * IMPORTANT: This field is critical as it determines the available languages
             * for all translatable fields (name, description, url). The financer's available_languages
             * property defines which languages must be provided for these translatable fields.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financer_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail): void {
                    $user = auth()->user();
                    if ($user && ! $user->hasAnyRole(['god', 'super_admin'])) {
                        $userFinancerIds = $user->financers()->pluck('financers.id')->toArray();
                        if (! in_array($value, $userFinancerIds)) {
                            $fail('You can only create links for your assigned financers.');
                        }
                    }
                },
            ],
        ]);

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * Currently, this request is authorized for all authenticated users.
     * Authorization is handled at the controller level.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the available languages for validation.
     *
     * This method retrieves the financer's available languages to determine which languages
     * should be required for translatable fields (name, description, url).
     *
     * IMPORTANT: The translatable attributes are strongly coupled with the financer's available_languages.
     * If the financer has specific available languages set, those exact languages will be required
     * for all translatable fields. If no financer is found or no languages are set, it falls back
     * to all Languages enum values.
     *
     * @return array<int, int|string> Array of language codes that should be validated
     */
    protected function getAvailableLanguages(): array
    {
        $financerId = $this->input('financer_id') ?? $this->header('x-financer-id');

        if ($financerId) {
            $financer = Financer::find($financerId);
            if ($financer && ! empty($financer->available_languages)) {
                return $financer->available_languages;
            }
        }

        return Languages::getValues();
    }
}
