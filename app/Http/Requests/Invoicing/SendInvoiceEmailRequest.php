<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class SendInvoiceEmailRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'cc' => ['sometimes', 'nullable', 'array'],
            'cc.*' => ['email'],
        ];
    }

    public function getEmail(): string
    {
        /** @var string $email */
        $email = $this->validated('email');

        return $email;
    }

    /**
     * @return array<int, string>
     */
    public function getCc(): array
    {
        /** @var array<int, string> $cc */
        $cc = $this->validated('cc', []);

        return $cc;
    }
}
