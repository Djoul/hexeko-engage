<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @group Integrations/Internal-Communication/Tags
 *
 * Data validation for tag creation and updating
 *
 * Note: financer_id is automatically assigned from auth()->user()->current_financer_id
 * and should not be provided in the request.
 */
class TagFormRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * The label of the tag in different languages.
             *
             * @var array
             *
             * @example {"en-GB": "Framework", "fr-BE": "Framework", "nl-BE": "Framework", "pt-PT": "Framework"}
             */
            'label' => ['required', 'array'],

            /**
             * English (UK) label of the tag.
             *
             * @var string
             *
             * @example "Framework"
             */
            'label.en-GB' => ['sometimes', 'string', 'max:50'],

            /**
             * French (Belgium) label of the tag.
             *
             * @var string
             *
             * @example "Cadre"
             */
            'label.fr-BE' => ['sometimes', 'string', 'max:50'],      /**
             * French (France) label of the tag.
             *
             * @var string
             *
             * @example "Cadre"
             */
            'label.fr-FR' => ['sometimes', 'string', 'max:50'],

            /**
             * Dutch (Belgium) label of the tag.
             *
             * @var string
             *
             * @example "Kader"
             */
            'label.nl-BE' => ['sometimes', 'string', 'max:50'],

            /**
             * Portuguese (Portugal) label of the tag.
             *
             * @var string
             *
             * @example "Quadro"
             */
            'label.pt-PT' => ['sometimes', 'string', 'max:50'],
        ];
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
