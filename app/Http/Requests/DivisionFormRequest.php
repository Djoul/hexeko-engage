<?php

namespace App\Http\Requests;

use App\Enums\Languages;
use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DivisionFormRequest extends FormRequest
{
    public function authorize(): bool
    {

        return true;
    }

    /**
     * @return array<string, array<int, string>>
     *                                           todo DivisionFormRequest
     */
    public function rules(): array
    {

        return [
            /**
             * The names.
             *
             * @var string
             *
             * @example Division France
             */
            'name' => ['required', 'string'],
            /**
             * The remarks.
             *
             * @var string
             *
             * @example This is a division
             */
            'remarks' => ['nullable', 'string'],
            /**
             * The country of the division (A division should be in a country but a country may have many divisions) .
             *
             * @var string
             *
             * @example FR
             */
            'country' => ['required', 'string', 'max:2'],
            /**
             * The currency.
             *
             * @var string
             *
             * @example EUR
             */
            'currency' => ['required', 'string', 'max:3'],
            /**
             * The timezone.
             *
             * @var string
             *
             * @example Europe/Paris
             */
            'timezone' => ['required', 'string'],
            /**
             * The language.
             *
             * @var string
             *
             * @example fr-FR
             */
            'language' => ['required', 'string', 'max:5', 'in:'.implode(',', Languages::getValues())],
            /**
             * The core package price in cents.
             *
             * @var int
             *
             * @example 5000
             */
            'core_package_price' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            /**
             * Optional modules configuration.
             *
             * @var array
             */
            'modules' => ['sometimes', 'array'],
            'modules.*.id' => [
                'sometimes',
                'string',
                'exists:modules,id',
            ],
            'modules.*.active' => ['required', 'boolean'],
            'modules.*.price_per_beneficiary' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $modules = $this->input('modules', []);
            if (! is_array($modules)) {
                return;
            }

            foreach ($modules as $index => $moduleData) {
                if (! is_array($moduleData)) {
                    continue;
                }
                if (array_key_exists('id', $moduleData) && array_key_exists('active', $moduleData)) {
                    $module = Module::find($moduleData['id']);

                    // Prevent deactivating core modules
                    if ($module instanceof Module && $module->is_core && ! $moduleData['active']) {
                        $indexStr = is_scalar($index) ? (string) $index : '';
                        $validator->errors()->add(
                            "modules.{$indexStr}.active",
                            'Core module cannot be deactivated'
                        );
                    }

                    // Prevent setting price on core modules (must always be null)
                    if ($module instanceof Module && $module->is_core && array_key_exists('price_per_beneficiary', $moduleData) && $moduleData['price_per_beneficiary'] !== null) {
                        $indexStr2 = is_scalar($index) ? (string) $index : '';
                        $validator->errors()->add(
                            "modules.{$indexStr2}.price_per_beneficiary",
                            'Core module price must always be null (included in core package price)'
                        );
                    }
                }
            }
        });
    }
}
