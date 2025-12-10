<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class ImportTranslationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:json',
                'max:10240', // 10MB max
            ],
            'interface_origin' => [
                'required',
                'string',
                Rule::in(['web', 'mobile']),
            ],
            'import_type' => [
                'required',
                'string',
                Rule::in(['multilingual', 'single']),
            ],
            'preview_only' => [
                'sometimes',
                'boolean',
            ],
            'update_existing_values' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A JSON file is required.',
            'file.mimes' => 'The file must be a JSON file.',
            'file.max' => 'The file size must not exceed 10MB.',
            'interface_origin.required' => 'The interface origin is required.',
            'interface_origin.in' => 'The interface origin must be either web or mobile.',
            'import_type.required' => 'The import type is required.',
            'import_type.in' => 'The import type must be either multilingual or single.',
        ];
    }

    /**
     * Get the uploaded file content
     */
    public function getFileContent(): string
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        $content = $file->get();

        return $content !== false ? $content : '';
    }

    /**
     * Get the original filename
     */
    public function getFilename(): string
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file->getClientOriginalName();
    }

    /**
     * Check if this is a preview request
     */
    public function isPreview(): bool
    {
        return (bool) $this->input('preview_only', false);
    }

    /**
     * Check if existing values should be updated
     */
    public function shouldUpdateExistingValues(): bool
    {
        return (bool) $this->input('update_existing_values', false);
    }
}
