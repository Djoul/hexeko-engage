<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SyncRolesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Permission check is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     * Single role system - accepts only one role.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /**
             * Single role name to assign to the user
             *
             * @var string
             *
             * @example "financer_admin"
             */
            'role' => 'required|string|exists:roles,name',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'The role field is required.',
            'role.string' => 'The role must be a string.',
            'role.exists' => 'The role ":input" does not exist.',
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
            'role' => 'role',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Merge the route parameter with the request data for validation
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }

    /**
     * Get additional validation rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('id', 'required|uuid|exists:users,id', function (): true {
            return true;
        });
    }
}
