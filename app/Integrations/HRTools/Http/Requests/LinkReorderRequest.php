<?php

namespace App\Integrations\HRTools\Http\Requests;

use App\Integrations\HRTools\Rules\LinksExistRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkReorderRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'links' => ['required', 'array', new LinksExistRule],
            'links.*.id' => ['required', 'string'],
            'links.*.position' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'links.required' => 'La liste des liens est requise.',
            'links.array' => 'La liste des liens doit être un tableau.',
            'links.*.id.required' => 'L\'identifiant du lien est requis.',
            'links.*.id.string' => 'L\'identifiant du lien doit être une chaîne de caractères.',
            'links.*.id.exists' => 'L\'identifiant du lien n\'existe pas.',
            'links.*.position.required' => 'La position du lien est requise.',
            'links.*.position.integer' => 'La position du lien doit être un nombre entier.',
            'links.*.position.min' => 'La position du lien doit être supérieure ou égale à 0.',
        ];
    }
}
