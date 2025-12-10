<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyTranslationMigrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'create_backup' => ['boolean'],
            'validate_checksum' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'create_backup.boolean' => 'The create backup field must be true or false.',
            'validate_checksum.boolean' => 'The validate checksum field must be true or false.',
        ];
    }
}
