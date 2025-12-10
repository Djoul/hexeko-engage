<?php

namespace App\Http\Requests;

use App\Models\Financer;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * @group Core/Financers
 *
 * Data validation for financer creation and updating
 */
class FinancerFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /**
             * The name of the financer.
             *
             * @var string
             *
             * @example "Hexeko"
             */
            'name' => [
                Rule::requiredIf(fn (): bool => $this->isMethod('POST')),
                'string',
                'max:255',
            ],

            /**
             * External identifier for the financer (JSON format).
             *
             * @var string
             *
             * @example {"external_system_id": "12345"}
             */
            'external_id' => ['nullable', 'json'],

            /**
             * The timezone of the financer.
             *
             * @var string
             *
             * @example "Europe/Paris"
             */
            'timezone' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * The registration number of the financer.
             *
             * @var string
             *
             * @example "123456789"
             */
            'registration_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * The country code where the financer is registered.
             *
             * @var string
             *
             * @example "FR"
             */
            'registration_country' => [
                'sometimes',
                'nullable',
                'string',
                'max:2',
            ],

            /**
             * The website URL of the financer.
             *
             * @var string
             *
             * @example "https://hexeko.com"
             */
            'website' => ['nullable', 'url'],

            /**
             * The IBAN (International Bank Account Number) of the financer.
             *
             * @var string
             *
             * @example "FR7630006000011234567890189"
             */
            'iban' => [
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * The VAT number of the financer.
             *
             * @var string
             *
             * @example "FR12345678901"
             */
            'vat_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            /**
             * The UUID of the representative user for this financer.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'representative_id' => [
                'nullable',
                'uuid',
                'exists:users,id',
            ],

            /**
             * The UUID of the division this financer belongs to.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'division_id' => [
                Rule::requiredIf(fn (): bool => $this->isMethod('POST')),
                'uuid',
                'exists:divisions,id',
            ],

            /**
             * The active status of the financer.
             *
             * @var bool
             *
             * @example true
             */
            'active' => [
                'nullable',
                'boolean',
            ],

            /**
             * The available languages for the financer.
             * This is an array of language codes (e.g., 'fr-BE', 'en-GB').
             * By default, it contains the financer's division language.
             *
             * @var array<string>
             *
             * @example ["fr-BE", "en-GB"]
             */
            'available_languages' => [
                'nullable',
                'array',
                'min:1',
            ],
            'available_languages.*' => [
                'string',
            ],

            /**
             * The status of the financer.
             *
             * @var string
             *
             * @example "active"
             */
            'status' => [
                'sometimes',
                'string',
                'in:active,pending,archived',
            ],

            /**
             * The BIC (Bank Identifier Code) of the financer.
             *
             * @var string
             *
             * @example "BNPAFRPPXXX"
             */
            'bic' => [
                'nullable',
                'string',
                'max:11',
                'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/',
            ],

            /**
             * The company number of the financer.
             *
             * @var string
             *
             * @example "BE0123456789"
             */
            'company_number' => [
                'sometimes',
                'string',
                'max:255',
            ],

            /**
             * The core package price in cents (overrides division price).
             *
             * @var int
             *
             * @example 4500
             */
            'core_package_price' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999999',
            ],

            /**
             * Optional modules configuration for the financer.
             *
             * @var array<array{id: string, active: bool, promoted?: bool, price_per_beneficiary?: int}>|null
             *
             * @example [{"id": "module-uuid", "active": true, "promoted": false, "price_per_beneficiary": 500}]
             */
            'modules' => ['sometimes', 'nullable', 'array', 'max:100'],
            'modules.*.id' => ['required_with:modules', 'string', 'uuid', 'exists:modules,id'],
            'modules.*.active' => ['required_with:modules', 'boolean'],
            'modules.*.promoted' => ['nullable', 'boolean'],
            'modules.*.price_per_beneficiary' => ['nullable', 'integer', 'min:0', 'max:999999'],

            /**
             * The logo image in base64 format.
             *
             * @var string|null
             *
             * @example "data:image/jpeg;base64,/9j/4AAQSkZJRgABA..."
             */
            'logo' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance for custom validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Validate modules
            $this->validateModules($validator);

            // Validate language deletion for update requests
            $this->validateLanguageDeletion($validator);
        });
    }

    /**
     * Validate module-related business rules
     */
    protected function validateModules(Validator $validator): void
    {
        $modules = $this->input('modules', []);
        if (! is_array($modules)) {
            return;
        }

        foreach ($modules as $index => $moduleData) {
            if (! is_array($moduleData)) {
                continue;
            }

            if (array_key_exists('id', $moduleData) && array_key_exists('active', $moduleData)) {
                $moduleId = is_string($moduleData['id']) ? $moduleData['id'] : null;
                if ($moduleId === null) {
                    continue;
                }

                $module = Module::find($moduleId);

                // Prevent deactivating core modules
                if ($module instanceof Module && $module->is_core && ! $moduleData['active']) {
                    $validator->errors()->add(
                        "modules.{$index}.active",
                        'Core module cannot be deactivated'
                    );
                }

                // Prevent setting price on core modules (must always be null)
                if ($module instanceof Module && $module->is_core && array_key_exists('price_per_beneficiary', $moduleData) && $moduleData['price_per_beneficiary'] !== null) {
                    $validator->errors()->add(
                        "modules.{$index}.price_per_beneficiary",
                        'Core module price must always be null (included in core package price)'
                    );
                }

                // Require price for active non-core modules
                if ($module instanceof Module && ! $module->is_core && $moduleData['active'] && (! array_key_exists('price_per_beneficiary', $moduleData) || $moduleData['price_per_beneficiary'] === null)) {
                    $validator->errors()->add(
                        "modules.{$index}.price_per_beneficiary",
                        'Active non-core modules must have a price'
                    );
                }
            }
        }
    }

    /**
     * Validate that languages in use cannot be removed
     */
    protected function validateLanguageDeletion(Validator $validator): void
    {
        // Only validate on update requests (PUT/PATCH)
        if (! $this->isMethod('PUT') && ! $this->isMethod('PATCH')) {
            return;
        }

        // Only validate if available_languages is explicitly provided in the request
        // Fix for UE-824: partial updates without available_languages should not trigger this validation
        if (! $this->has('available_languages')) {
            return;
        }

        // Get the financer being updated from route parameter
        $financerId = $this->route('financer');
        if (! is_string($financerId)) {
            return;
        }

        $financer = Financer::find($financerId);
        if (! $financer instanceof Financer) {
            return;
        }

        // Get current and new languages
        $currentLanguages = $financer->available_languages ?? [];
        $newLanguages = $this->input('available_languages', []);

        if (! is_array($currentLanguages) || ! is_array($newLanguages)) {
            return;
        }

        // Determine which languages are being removed
        $removedLanguages = array_diff($currentLanguages, $newLanguages);

        if ($removedLanguages === []) {
            return; // No languages removed, validation passes
        }

        // Check if any user is using a language being removed
        $usersWithRemovedLanguage = DB::table('financer_user')
            ->where('financer_id', $financer->id)
            ->whereIn('language', $removedLanguages)
            ->exists();

        if ($usersWithRemovedLanguage) {
            $validator->errors()->add(
                'available_languages',
                'Cannot remove a language that is currently used by users. Please update user language preferences first.'
            );
        }
    }
}
