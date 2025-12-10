<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceItemRequest extends FormRequest
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
            'item_type' => ['required', Rule::in(InvoiceItemType::getValues())],
            'module_id' => [
                'required_if:item_type,'.InvoiceItemType::MODULE,
                'nullable',
                'uuid',
            ],
            'unit_price_htva' => ['required', 'integer', 'min:0'],
            'quantity' => ['required', 'integer', 'min:1'],
            'label' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'array'],
            'beneficiaries_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
