<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Http\Requests;

use App\Integrations\Payments\Stripe\Models\StripePayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * @deprecated
 * Payment confirmation should be done via Stripe.js on frontend
 * 2025-01-05
 */
class ConfirmPaymentIntentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // User must be authenticated
        // Additional authorization logic could be added here
        // For example, check if the user owns the payment intent
        // or has permission to confirm payments
        return (bool) $this->user();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The payment intent ID is passed in the URL, not in the request body
            // Additional fields could be added here if needed, such as:
            // 'payment_method_id' => 'sometimes|string',
            // 'return_url' => 'sometimes|url',
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
            // Custom validation logic
            // For example, verify the payment intent belongs to the authenticated user
            $paymentIntentId = $this->route('paymentIntentId');

            if ($paymentIntentId && ! $this->userOwnsPaymentIntent($paymentIntentId)) {
                $validator->errors()->add('payment_intent', 'You do not have permission to confirm this payment.');
            }
        });
    }

    /**
     * Check if the user owns the payment intent
     */
    protected function userOwnsPaymentIntent(string $paymentIntentId): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Check if there's a payment record for this user and payment intent
        return StripePayment::where('stripe_payment_id', $paymentIntentId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
