<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Http\Requests;

use App\Integrations\Payments\Stripe\DTO\CheckoutSessionDTO;
use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:1', 'max:99999.99'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:eur,usd'],
            'credit_type' => ['required', 'string', 'in:voucher_credit,premium_credit'],
            'credit_amount' => ['required', 'integer', 'min:1'],
            'success_url' => ['required', 'url', 'max:2048'],
            'cancel_url' => ['required', 'url', 'max:2048'],
            'product_name' => ['sometimes', 'string', 'max:255'],
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
            'amount.min' => 'The payment amount must be at least 1.',
            'amount.max' => 'The payment amount cannot exceed 99,999.99.',
            'currency.in' => 'The currency must be either EUR or USD.',
            'credit_type.required' => 'The credit type is required.',
            'credit_type.in' => 'The credit type must be either voucher_credit or premium_credit.',
            'credit_amount.required' => 'The credit amount is required.',
            'credit_amount.integer' => 'The credit amount must be an integer.',
            'credit_amount.min' => 'The credit amount must be at least 1.',
            'success_url.required' => 'The success URL is required.',
            'success_url.url' => 'The success URL must be a valid URL.',
            'cancel_url.required' => 'The cancel URL is required.',
            'cancel_url.url' => 'The cancel URL must be a valid URL.',
        ];
    }

    public function toDto(): CheckoutSessionDTO
    {
        /** @var array{amount: float, currency?: string, credit_type: string, credit_amount: int, success_url: string, cancel_url: string, product_name?: string} $validated */
        $validated = $this->validated();

        return new CheckoutSessionDTO(
            userId: (string) auth()->id(),
            amount: $validated['amount'],
            currency: $validated['currency'] ?? 'eur',
            creditType: $validated['credit_type'],
            creditAmount: $validated['credit_amount'],
            successUrl: $validated['success_url'],
            cancelUrl: $validated['cancel_url'],
            productName: $validated['product_name'] ?? 'Crédits Engage',
        );
    }
}
