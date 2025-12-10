<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Http\Requests;

use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseVoucherRequest extends FormRequest
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
            'payment_method' => [
                'required',
                'string',
                Rule::in(['balance', 'stripe', 'mixed', 'auto']),
            ],
            'use_balance' => [
                'sometimes',
                'boolean',
            ],
            'stripe_payment_id' => [
                'sometimes',
                'string',
                'regex:/^pi_/', // Payment Intent ID format
            ],
            'balance_amount' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'order_recovered_id' => [
                'sometimes',
                'uuid',
                'exists:int_vouchers_amilon_orders,id',
                Rule::exists('int_vouchers_amilon_orders', 'id')->where(function ($query): void {
                    $query->where('status', OrderStatus::CANCELLED)
                        ->where('user_id', $this->user()->id);
                }),
            ],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Please select a voucher product',
            'product_id.exists' => 'The selected product does not exist',
            'payment_method.in' => 'Invalid payment method. Valid options are: balance, stripe, mixed, or auto',
            'stripe_payment_id.regex' => 'Invalid Stripe payment ID format',
            'balance_amount.numeric' => 'Balance amount must be a valid number',
            'balance_amount.min' => 'Balance amount cannot be negative',
            'order_recovered_id.exists' => 'The order to recover does not exist or is not eligible for recovery',
        ];
    }
}
