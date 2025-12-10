<?php

namespace App\Integrations\Vouchers\Amilon\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by the controller's RequiresPermission attribute
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_code' => ['required', 'string', 'exists:int_vouchers_amilon_products,product_code'],
            'quantity' => ['required', 'numeric', 'min:1', 'max:1000'], // Assuming a maximum amount of 1000
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
            'product_code.required' => 'The product code is required',
            'product_code.exists' => 'The selected product does not exist',
            'quantity.required' => 'The quantity is required',
            'quantity.numeric' => 'The quantity must be a number',
            'quantity.min' => 'The quantity must be at least :min',
            'quantity.max' => 'The quantity cannot exceed :max',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert quantity to integer if it's a string
        if (is_string($this->quantity)) {
            $this->merge([
                'quantity' => (int) $this->quantity,
            ]);
        }
    }
}
