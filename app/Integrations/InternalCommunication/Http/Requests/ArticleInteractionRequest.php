<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Requests;

use App\Integrations\InternalCommunication\Enums\ReactionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleInteractionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reaction' => ['nullable', 'string', Rule::in(ReactionTypeEnum::getValues())],
            'article_translation_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
