<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateInvoiceStatusRequest extends FormRequest
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
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'uuid'],
            'status' => ['required', Rule::in([
                InvoiceStatus::CONFIRMED,
                InvoiceStatus::SENT,
                InvoiceStatus::PAID,
                InvoiceStatus::CANCELLED,
            ])],
        ];
    }
}
