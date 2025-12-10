<?php

namespace App\Http\Requests;

use App\Enums\IDP\RoleDefaults;
use App\Rules\UniqueEmailPerActiveFinancer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * @group Core/InvitedUsers
 *
 * Data validation for invited user creation and updating
 */
class InvitedUserFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by the middleware
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
             * The UUID of the invited user (required for updates).
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => [
                new RequiredIf(request()->routeIs('invited-users.update')),
                'string',
                'uuid',
            ],

            /**
             * The email address of the invited user.
             *
             * Must be unique per financer for active users.
             * The same email can exist for different financers or for inactive users.
             *
             * @var string
             *
             * @example "user@example.com"
             */
            'email' => [
                'required',
                'string',
                'email',
                new UniqueEmailPerActiveFinancer(
                    financerId: $this->input('financer_id', ''),
                    ignoreUserId: $this->input('id')
                ),
            ],

            /**
             * The first name of the invited user.
             *
             * @var string
             *
             * @example "John"
             */
            'first_name' => ['required', 'string', 'max:255'],

            /**
             * The last name of the invited user.
             *
             * @var string
             *
             * @example "Doe"
             */
            'last_name' => ['required', 'string', 'max:255'],

            /**
             * The financer ID that the invited user belongs to.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'financer_id' => ['required', 'string', 'uuid', 'exists:financers,id'],

            /**
             * The phone number of the invited user (optional).
             *
             * @var string|null
             *
             * @example "+33612345678"
             */
            'phone' => ['nullable', 'string', 'min:8', 'max:20'],

            /**
             * SIRH identifier for the invited user.
             *
             * @var string|null
             *
             * @example "12345"
             */
            'sirh_id' => ['nullable', 'string'],

            /**
             * External identifier for the invited user.
             *
             * @var string|null
             *
             * @example "EMP-12345"
             */
            'external_id' => ['nullable', 'string', 'max:255'],

            /**
             * Additional JSON data for the invited user.
             *
             * @var array|null
             *
             * @example {"department": "IT", "position": "Developer"}
             */
            'extra_data' => ['nullable', 'array'],

            /**
             * The intended role for the invited user (optional).
             *
             * @var string|null
             *
             * @example "financer_admin"
             */
            'intended_role' => [
                'nullable',
                'string',
                Rule::in(RoleDefaults::getValues()),
            ],
        ];
    }
}
