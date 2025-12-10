<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Http\Requests;

use App\Integrations\Vouchers\Amilon\Services\StripePaymentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @deprecated
 * Use App\Integrations\Payments\Stripe\Http\Requests\CreatePaymentIntentRequest instead
 * 2025-01-05
 */
class CreatePaymentIntentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'uuid',
                'exists:int_vouchers_amilon_products,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:'.StripePaymentService::MIN_AMOUNT,
                'max:'.StripePaymentService::MAX_AMOUNT,
                Rule::in(array_merge(
                    StripePaymentService::ALLOWED_AMOUNTS,
                    $this->allowCustomAmount() ? [] : StripePaymentService::ALLOWED_AMOUNTS
                )),
            ],
            'metadata' => [
                'sometimes',
                'array',
                'max:10', // Limit metadata fields
            ],
            'metadata.*' => [
                'string',
                'max:500', // Limit metadata value length
            ],
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
            'product_id.required' => 'Please select a product.',
            'product_id.exists' => 'The selected product is invalid.',
            'amount.required' => 'Please specify an amount.',
            'amount.numeric' => 'The amount must be a number.',
            'amount.min' => 'The minimum amount is :min EUR.',
            'amount.max' => 'The maximum amount is :max EUR.',
            'amount.in' => 'Please select one of the predefined amounts: '.implode(', ', StripePaymentService::ALLOWED_AMOUNTS).' EUR.',
            'metadata.array' => 'Metadata must be an array.',
            'metadata.max' => 'Too many metadata fields (maximum :max allowed).',
            'metadata.*.string' => 'Metadata values must be strings.',
            'metadata.*.max' => 'Metadata value is too long (maximum :max characters).',
        ];
    }

    /**
     * Check if custom amounts are allowed (can be configured per financer)
     */
    protected function allowCustomAmount(): bool
    {
        // This could check financer settings, user permissions, etc.
        // For now, we'll only allow predefined amounts
        return false;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure amount is numeric
        if ($this->has('amount')) {
            $amount = $this->input('amount');
            $this->merge([
                'amount' => is_numeric($amount) ? (float) $amount : $amount,
            ]);
        }
    }
}
