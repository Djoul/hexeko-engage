<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmInvoiceRequest extends FormRequest
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
        return [];
    }
}
