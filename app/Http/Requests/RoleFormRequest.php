<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * @group Core/Roles
 *
 * Data validation for role creation and updating
 */
class RoleFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            /**
             * The UUID of the role (required for updates).
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => [
                new RequiredIf(request()->routeIs('roles.update')),
                'string',
                'uuid'],

            /**
             * The UUID of the team this role belongs to.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'team_id' => ['nullable', 'uuid', 'exists:teams,id'],

            /**
             * The name of the role.
             *
             * @var string
             *
             * @example "Administrator"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * The guard name for the role.
             *
             * @var string
             *
             * @example "api"
             */
            'guard_name' => ['required', 'string', 'max:255'],

            /**
             * The creation date of the role.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'created_at' => ['nullable', 'date'],

            /**
             * The last update date of the role.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'updated_at' => ['nullable', 'date'],
        ];
    }
}
