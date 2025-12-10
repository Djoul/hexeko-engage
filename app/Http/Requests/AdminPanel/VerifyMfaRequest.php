<?php

namespace App\Http\Requests\AdminPanel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VerifyMfaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'email'],
            'mfa_code' => ['required', 'string', 'min:6', 'max:6'],
            'session' => ['required', 'string'],
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
            'username.required' => 'Email is required.',
            'username.email' => 'Please provide a valid email address.',
            'mfa_code.required' => 'MFA code is required.',
            'mfa_code.min' => 'MFA code must be 6 digits.',
            'mfa_code.max' => 'MFA code must be 6 digits.',
            'session.required' => 'Session is required for MFA verification.',
        ];
    }
}
