<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\Languages;
use App\Rules\BelongsToCurrentFinancer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @group Core/Users
 *
 * Data validation for user creation and updating
 */
class UserFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Adjust this based on authorization logic
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
             * The UUID of the user (required for updates).
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => [// required if Update
                'string',
                'uuid',
            ],

            /**
             * The email address of the user.
             *
             * @var string
             *
             * @example "user@example.com"
             */
            'email' => ['sometimes', 'string', 'email', Rule::unique('users')->ignore($this->route('id'))],

            /**
             * The Cognito ID of the user.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'cognito_id' => ['nullable', 'uuid'],

            /**
             * The first name of the user.
             *
             * @var string
             *
             * @example "John"
             */
            'first_name' => ['nullable', 'string', 'max:255'],

            /**
             * The last name of the user.
             *
             * @var string
             *
             * @example "Doe"
             */
            'last_name' => ['nullable', 'string', 'max:255'],

            /**
             * The description of the user.
             *
             * @var string|null
             *
             * @example "Senior developer with 5 years of experience"
             */
            'description' => ['nullable', 'string'],

            //            /**
            //             * Whether the user is required to change their email.
            //             *
            //             * @var bool
            //             *
            //             * @example false
            //             */
            //            'force_change_email' => ['required', 'boolean'],

            /**
             * The birthdate of the user.
             *
             * @var string
             *
             * @example "1990-01-01"
             */
            'birthdate' => ['nullable', 'date'],

            /**
             * Whether the user has confirmed the terms of service.
             *
             * @var bool
             *
             * @example true
             */
            'terms_confirmed' => ['boolean'],

            /**
             * Whether the user account is enabled.
             *
             * @var bool
             *
             * @example true
             */
            'enabled' => ['sometimes', 'boolean'],

            /**
             * The locale preference of the user. (deprecated)
             *
             * @deprecated Use financers.*.pivot.language instead to update language per financer
             *
             * @var string
             *
             * @example "fr-FR"
             */
            'locale' => ['sometimes', 'string', 'max:5', 'in:'.implode(',', Languages::asArray())],

            /**
             * The currency preference of the user.
             *
             * @var string
             *
             * @example "EUR"
             */
            'currency' => ['sometimes', 'string', 'size:3'],

            /**
             * The timezone preference of the user.
             *
             * @var string
             *
             * @example "Europe/Paris"
             */
            'timezone' => ['nullable', 'string', 'max:255'],

            /**
             * The Stripe customer ID of the user.
             *
             * @var string
             *
             * @example "cus_123456789"
             */
            'stripe_id' => ['nullable', 'string', 'max:255'],

            /**
             * External identifier for the user.
             *
             * @var mixed
             *
             * @example {"external_system_id": "12345"}
             */
            'sirh_id' => ['nullable'],

            /**
             * The date of the user's last login.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'last_login' => ['nullable', 'date'],

            /**
             * Whether the user has opted in to marketing communications.
             *
             * @var bool
             *
             * @example true
             */
            'opt_in' => ['sometimes', 'boolean'],

            /**
             * Le numéro de téléphone de l'utilisateur (optionnel).
             *
             * @var string|null
             *
             * @example "+33612345678"
             */
            'phone' => ['nullable', 'string', 'min:8', 'max:20'],

            /**
             * The remember token for the user.
             *
             * @var string
             *
             * @example "abc123def456" //pragma: allowlist secret
             */
            'remember_token' => ['nullable', 'string', 'max:100'],

            /**
             * The financers associated with the user.
             *
             * @var array
             *
             * @example [{"id": "123e4567-e89b-12d3-a456-426614174000", "pivot": {"active": true}}]
             */
            'financers' => ['sometimes', 'array'],

            /**
             * The ID of each financer associated with the user.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financers.*.id' => ['required_with:financers.*', 'string', 'uuid', 'exists:financers,id'],

            /**
             * Whether the association between the user and financer is active.
             *
             * @var bool
             *
             * @example true
             */
            'financers.*.pivot.active' => ['sometimes', 'boolean'],

            /**
             * The work mode of the user.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financers.*.pivot.work_mode_id' => ['nullable', 'uuid', new BelongsToCurrentFinancer('work_modes')],

            /**
             * The job title of the user.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financers.*.pivot.job_title_id' => ['nullable', 'uuid', new BelongsToCurrentFinancer('job_titles')],

            /**
             * The job level of the user.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financers.*.pivot.job_level_id' => ['nullable', 'uuid', new BelongsToCurrentFinancer('job_levels')],

            /**
             * The date the user started working for the financer.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'financers.*.pivot.started_at' => ['nullable', 'date'],

            /**
             * The language preference for this financer.
             *
             * @var string
             *
             * @example "fr-FR"
             */
            'financers.*.pivot.language' => ['nullable', 'string', 'in:'.implode(',', Languages::asArray())],

            /**
             * The creation date of the user.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'created_at' => ['nullable', 'date'],

            /**
             * The last update date of the user.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'updated_at' => ['nullable', 'date'],

            /**
             * The deletion date of the user (for soft deletes).
             *
             * @var string
             *
             * @example "2023-02-01T10:00:00Z"
             */
            'deleted_at' => ['nullable', 'date'],

            /**
             * The profile image as a base64 string.
             *
             * @var string|null
             *
             * @example "data:image/jpeg;base64,/9j/4AAQSkZJRgABA..."
             */
            'profile_image' => [
                'sometimes',
                'nullable',
                'string',
            ],

            /**
             * The departments associated with the user.
             *
             * @var array<string>|null
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000"]
             */
            'departments' => ['nullable', 'array'],
            'departments.*' => ['uuid', new BelongsToCurrentFinancer('departments')],

            /**
             * The sites associated with the user.
             *
             * @var array<string>|null
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000"]
             */
            'sites' => ['nullable', 'array'],
            'sites.*' => ['uuid', new BelongsToCurrentFinancer('sites')],

            /**
             * The managers associated with the user.
             *
             * @var array<string>|null
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000"]
             */
            'managers' => ['nullable', 'array'],
            'managers.*' => ['uuid', new BelongsToCurrentFinancer('users', 'user_id', 'financer_user', 'financer_id')],

            /**
             * The contract types associated with the user.
             *
             * @var array<string>|null
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000"]
             */
            'contract_types' => ['nullable', 'array'],
            'contract_types.*' => ['uuid', new BelongsToCurrentFinancer('contract_types')],

            /**
             * The tags associated with the user.
             *
             * @var array<string>|null
             *
             * @example ["123e4567-e89b-12d3-a456-426614174000"]
             */
            'tags' => ['nullable', 'array'],
            'tags.*' => ['uuid', new BelongsToCurrentFinancer('tags')],

            /**
             * The gender of the user.
             *
             * @var string
             *
             * @example "male"
             */
            'gender' => ['nullable', 'string', 'in:'.implode(',', Gender::asArray())],
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    //    protected function after(): void
    //    {
    //        //remove password_confirmation from validated data
    //        $this->merge([
    //            'boom' => $this->input('password'),
    //        ]);
    //
    //    }
}
