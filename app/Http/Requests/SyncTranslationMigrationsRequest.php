<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrigineInterfaces;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncTranslationMigrationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'interface' => ['required', Rule::in([
                OrigineInterfaces::MOBILE,
                OrigineInterfaces::WEB_FINANCER,
                OrigineInterfaces::WEB_BENEFICIARY,
            ])],
            'auto_process' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interface.required' => 'The interface field is required.',
            'interface.enum' => 'The selected interface is invalid.',
            'auto_process.boolean' => 'The auto process field must be true or false.',
        ];
    }
}
