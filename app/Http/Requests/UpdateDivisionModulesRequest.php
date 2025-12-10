<?php

namespace App\Http\Requests;

use App\Models\Division;
use App\Models\Module;
use DB;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Request validation for updating division modules.
 *
 * @property array{modules: array<array{id: string, active: bool, price_per_beneficiary?: int}>} $validated
 */
class UpdateDivisionModulesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by RequiresPermission attribute
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /**
             * The core package price in cents.
             *
             * @var int
             *
             * @example 5000
             */
            'core_package_price' => ['present', 'nullable', 'integer', 'min:0', 'max:9999999'],
            /**
             * Optional modules configuration.
             *
             * @var array
             */
            'modules' => 'required|array',
            'modules.*.id' => [
                'required',
                'string',
                'exists:modules,id',
            ],
            'modules.*.active' => 'required|boolean',
            'modules.*.price_per_beneficiary' => 'nullable|integer|min:0|max:999999',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'modules.required' => 'Modules array is required',
            'modules.array' => 'Modules must be an array',
            'modules.*.id.required' => 'Module ID is required',
            'modules.*.id.exists' => 'Module not found',
            'modules.*.active.required' => 'Active status is required',
            'modules.*.active.boolean' => 'Active must be true or false',
            'modules.*.price_per_beneficiary.integer' => 'Price must be an integer in cents',
            'modules.*.price_per_beneficiary.min' => 'Price cannot be negative',
            'modules.*.price_per_beneficiary.max' => 'Price exceeds maximum allowed',
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

            // Get division ID from route parameter
            $divisionId = $this->route('division');
            if (! $divisionId instanceof Division) {
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

                    // Prevent deactivating non-core modules if enabled in at least one financer
                    if ($module instanceof Module && ! $module->is_core && ! $moduleData['active']) {
                        // Count how many financers in this division have this module active
                        $financersUsingModule = DB::table('financer_module')
                            ->join('financers', 'financers.id', '=', 'financer_module.financer_id')
                            ->where('financers.division_id', $divisionId->id)
                            ->where('financer_module.module_id', $module->id)
                            ->where('financer_module.active', true)
                            ->count();

                        if ($financersUsingModule > 0) {
                            $indexStr = is_scalar($index) ? (string) $index : '';
                            $validator->errors()->add(
                                "modules.{$indexStr}.active",
                                "Cannot deactivate module: it is currently enabled in {$financersUsingModule} financer(s) of this division"
                            );
                        }
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
