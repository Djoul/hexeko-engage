<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListInvoicesRequest extends FormRequest
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
            /**
             * Filter invoices by status
             *
             * @example "draft"
             */
            'status' => ['sometimes', 'nullable', Rule::in(InvoiceStatus::getValues())],

            /**
             * Filter invoices by recipient UUID
             *
             * @example "550e8400-e29b-41d4-a716-446655440000"
             */
            'recipient_id' => ['sometimes', 'nullable', 'uuid'],

            /**
             * Filter invoices with billing period starting from this date
             *
             * @example "2024-01-01"
             */
            'billing_period_start' => ['sometimes', 'nullable', 'date'],

            /**
             * Filter invoices with billing period ending before this date
             *
             * @example "2024-12-31"
             */
            'billing_period_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:billing_period_start'],

            /**
             * Number of invoices per page
             *
             * @example 20
             */
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],

            /**
             * Page for pagination
             */
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
