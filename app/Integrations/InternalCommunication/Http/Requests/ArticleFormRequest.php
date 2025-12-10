<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Requests;

use App\Enums\Languages;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * @group Integrations/Internal-Communication/Articles
 *
 * Data validation for article creation and updating
 *
 * Note: financer_id is automatically assigned from auth()->user()->current_financer_id
 * and should not be provided in the request.
 */
class ArticleFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->can('create_article') || $user->can('update_article'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            /**
             * The UUID of the author of the article.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'author_id' => ['sometimes', 'uuid', 'exists:users,id'],

            /**
             * The UUID of the segment associated with the article.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'segment_id' => ['sometimes', 'nullable', 'uuid', 'exists:segments,id'],

            /**
             * The title of the article.
             *
             * @var string
             *
             * @example "Introduction à Laravel"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * The response from the LLM used to generate the article.
             */
            'llm_response' => ['sometimes', 'string', 'nullable'],

            /**
             * The content of the article.
             *
             * @var array
             *
             * @example ["type" => "doc", "content" => [...]]
             */
            'content' => ['required', 'array'],
            'content.type' => ['required', 'string', 'in:doc'],
            'content.content' => ['required', 'array'],

            /**
             * The status of the article (draft, published, pending, deleted).
             *
             * @var string
             *
             * @example "published"
             */
            'status' => ['sometimes', 'string', 'in:'.implode(',', StatusArticleEnum::getValues())],

            /**
             * The language of the article.
             *
             * @var string
             *
             * @example "fr-BE"
             */
            'language' => ['required', 'string', 'in:'.implode(',', Languages::getValues())],

            /**
             * The tags associated with the article.
             *
             * Note: Tags are automatically filtered by HasFinancer global scope,
             * so only tags belonging to the user's current financer are accessible.
             *
             * @var array
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000", "123e4567-e89b-12d3-a456-426614174001"]
             */
            'tags' => ['sometimes', 'array'],

            /**
             * Individual tag validation.
             *
             * Validates that each tag:
             * 1. Is a valid UUID
             * 2. Exists in the tags table
             * 3. Belongs to the same financer as the current user
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'tags.*' => [
                'uuid',
                'exists:int_communication_rh_tags,id',
                function ($attribute, $value, $fail): void {
                    $financerId = activeFinancerID();
                    $tag = Tag::withoutGlobalScopes()
                        ->where('id', $value)
                        ->where('financer_id', $financerId)
                        ->first();

                    if (! $tag) {
                        $fail('The selected tag does not belong to your organization.');
                    }
                },
            ],

            /**
             * The publication date of the article.
             *
             * @var string
             *
             * @example "2023-04-15T14:30:00Z"
             */
            'published_at' => ['nullable', 'date'],
        ];

        if ($this->isMethod('POST') || $this->isMethod('PUT')) {
            /**
             * The illustration image for the article (JPG, PNG, etc.).
             */
            $rules['illustration'] = ['sometimes',
                'nullable',
                'string']; // 5MB max

            /**
             * The version number to use for changing the active illustration.
             * When provided, the illustration from that version will become active.
             *
             * @example 2
             */
            $rules['illustration_version_number'] = ['sometimes', 'nullable', 'integer', 'min:1'];
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $this->replace($this->getOriginalInput());
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalInput(): array
    {
        $decoded = json_decode($this->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
