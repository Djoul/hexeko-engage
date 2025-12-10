<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class UnregisterDeviceRequest extends FormRequest
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
            'subscription_ids' => ['required', 'array', 'min:1'],
            'subscription_ids.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'subscription_ids.required' => 'At least one subscription ID is required.',
            'subscription_ids.array' => 'Subscription IDs must be provided as an array.',
            'subscription_ids.*.required' => 'Each subscription ID is required.',
            'subscription_ids.*.string' => 'Each subscription ID must be a string.',
        ];
    }
}
