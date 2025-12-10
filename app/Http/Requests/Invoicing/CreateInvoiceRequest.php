<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
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
             * Type of recipient entity
             *
             * @example "division"
             */
            'recipient_type' => ['required', Rule::in(['division', 'financer'])],

            /**
             * UUID of the recipient entity
             *
             * @example "550e8400-e29b-41d4-a716-446655440000"
             */
            'recipient_id' => ['required', 'uuid'],

            /**
             * Start date of the billing period
             *
             * @example "2024-01-01"
             */
            'billing_period_start' => ['required', 'date'],

            /**
             * End date of the billing period
             *
             * @example "2024-01-31"
             */
            'billing_period_end' => ['required', 'date', 'after_or_equal:billing_period_start'],

            /**
             * VAT rate as percentage
             *
             * @example 21.0
             */
            'vat_rate' => ['required', 'numeric', 'min:0'],

            /**
             * Currency code (ISO 4217)
             *
             * @example "EUR"
             */
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],

            /**
             * Due date for payment
             *
             * @example "2024-02-29"
             */
            'due_date' => ['sometimes', 'nullable', 'date'],

            /**
             * Additional notes for the invoice
             */
            'notes' => ['sometimes', 'nullable', 'string'],

            /**
             * Additional metadata
             */
            'metadata' => ['sometimes', 'nullable', 'array'],

            /**
             * Array of invoice items
             */
            'items' => ['sometimes', 'nullable', 'array'],

            /**
             * Type of invoice item
             *
             * @example "module"
             */
            'items.*.item_type' => ['required_with:items', Rule::in(InvoiceItemType::getValues())],

            /**
             * Module UUID (required when item_type is 'module')
             *
             * @example "650e8400-e29b-41d4-a716-446655440000"
             */
            'items.*.module_id' => ['sometimes', 'nullable', 'uuid'],

            /**
             * Unit price excluding VAT (in cents)
             *
             * @example 10000
             */
            'items.*.unit_price_htva' => ['required_with:items', 'integer', 'min:0'],

            /**
             * Quantity of this item
             *
             * @example 5
             */
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],

            /**
             * Localized label
             */
            'items.*.label' => ['sometimes', 'nullable', 'array'],

            /**
             * Localized description
             */
            'items.*.description' => ['sometimes', 'nullable', 'array'],

            /**
             * Number of beneficiaries for this item
             *
             * @example 42
             */
            'items.*.beneficiaries_count' => ['sometimes', 'nullable', 'integer', 'min:0'],

            /**
             * Additional item metadata
             */
            'items.*.metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                if (($item['item_type'] ?? null) === InvoiceItemType::MODULE && empty($item['module_id'])) {
                    $validator->errors()->add("items.$index.module_id", 'The module_id field is required when item_type is module.');
                }
            }
        });
    }
}
