<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportInvoicesExcelRequest extends FormRequest
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
            'filters' => ['sometimes', 'nullable', 'array'],
            'date_start' => ['sometimes', 'nullable', 'date'],
            'date_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:date_start'],
            'status' => ['sometimes', 'nullable', Rule::in(InvoiceStatus::getValues())],
        ];
    }
}
