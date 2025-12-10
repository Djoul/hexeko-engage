<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:10'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:eur,usd,gbp'],
            'credit_amount' => ['sometimes', 'integer', 'min:0'],
            'credit_type' => ['sometimes', 'string', 'in:ai_token,sms,email,cash'],
            'metadata' => ['sometimes', 'array'],
            'metadata.*' => ['string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'The payment amount is required.',
            'amount.numeric' => 'The payment amount must be a number.',
            'amount.min' => 'The payment amount must be at least 0.01.',
            'currency.size' => 'The currency must be a 3-letter code.',
            'currency.in' => 'The selected currency is not supported.',
            'credit_amount.integer' => 'The credit amount must be an integer.',
            'credit_amount.min' => 'The credit amount cannot be negative.',
            'credit_type.in' => 'The selected credit type is invalid.',
        ];
    }
}
