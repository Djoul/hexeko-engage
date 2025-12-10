<?php

namespace App\Http\Requests;

use App\Models\Module;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateFinancerModuleRequest extends FormRequest
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
            'module_id' => [
                'required',
                'string',
                'exists:modules,id',
                function ($attribute, $value, $fail): void {
                    $module = Module::find($value);
                    if ($module instanceof Module && $module->is_core) {
                        $fail('Core module cannot be deactivated');
                    }
                },
            ],
            'financer_id' => 'required|string|exists:financers,id',
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
            'module_id.required' => 'Module ID is required',
            'module_id.exists' => 'Module not found',
            'financer_id.required' => 'Financer ID is required',
            'financer_id.exists' => 'Financer not found',
        ];
    }
}
