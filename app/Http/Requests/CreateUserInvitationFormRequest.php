<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for creating user invitations.
 * Sprint 3 - API Integration: Validates invitation creation request data.
 *
 * @group Core/Invitations
 */
class CreateUserInvitationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
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
             * The email address of the invited user.
             *
             * @var string
             *
             * @example "newuser@example.com"
             */
            'email' => ['required', 'string', 'email', 'max:255'],

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
             * The team ID the invited user belongs to (optional).
             *
             * @var string|null
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'team_id' => ['nullable', 'string', 'uuid'],

            /**
             * Additional metadata for the invitation (optional).
             *
             * @var array|null
             *
             * @example {"department": "IT", "position": "Developer"}
             */
            'metadata' => ['nullable', 'array'],

            /**
             * Number of days before invitation expires (optional, default 7).
             *
             * @var int|null
             *
             * @example 14
             */
            'expiration_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }
}
