<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;

class IndexArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'language' => ['sometimes', 'string', 'max:10'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['uuid'],
            'is_favorite' => ['sometimes', 'boolean'],
            'is_read' => ['sometimes', 'boolean'],
            'published_from' => ['sometimes', 'date'],
            'published_to' => ['sometimes', 'date', 'after_or_equal:published_from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'order-by' => ['sometimes', 'string', 'in:title,published_at,created_at'],
            'order-by-desc' => ['sometimes', 'string', 'in:title,published_at,created_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tags.*.uuid' => 'Each tag must be a valid UUID.',
            'published_to.after_or_equal' => 'The published_to date must be after or equal to published_from.',
            'per_page.max' => 'The per_page value cannot exceed 100.',
            'order-by.in' => 'The order-by field must be one of: title, published_at, created_at.',
            'order-by-desc.in' => 'The order-by-desc field must be one of: title, published_at, created_at.',
        ];
    }
}
