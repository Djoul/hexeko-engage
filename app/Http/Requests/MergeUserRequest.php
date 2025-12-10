<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergeUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'exists:users,email'],
            'invited_user_id' => [
                'required',
                'uuid',
                Rule::exists('users', 'id')->where('invitation_status', 'pending'),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email doit être une adresse email valide',
            'email.exists' => 'Aucun utilisateur trouvé avec cet email',
            'invited_user_id.required' => 'L\'ID de l\'utilisateur invité est requis',
            'invited_user_id.uuid' => 'L\'ID de l\'utilisateur invité doit être un UUID valide',
            'invited_user_id.exists' => 'Aucun utilisateur invité trouvé avec cet ID',
        ];
    }
}
