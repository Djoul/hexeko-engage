<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class MarkInvoiceAsPaidRequest extends FormRequest
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
            'amount_paid' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
