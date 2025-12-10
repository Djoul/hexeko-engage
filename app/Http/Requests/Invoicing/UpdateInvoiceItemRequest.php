<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceItemRequest extends FormRequest
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
            'item_type' => ['sometimes', 'nullable', Rule::in(InvoiceItemType::getValues())],
            'module_id' => ['sometimes', 'nullable', 'uuid'],
            'unit_price_htva' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'label' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'array'],
            'beneficiaries_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (($this->input('item_type') ?? null) === 'module' && empty($this->input('module_id'))) {
                $validator->errors()->add('module_id', 'The module_id field is required when item_type is module.');
            }
        });
    }
}
