<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * @group Core/Teams
 *
 * Data validation for team creation and updating
 */
class TeamFormRequest extends FormRequest
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
        $isUpdating = request()->routeIs('teams.update');

        return [
            /**
             * The UUID of the team (required for updates).
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => [
                new RequiredIf($isUpdating),
                'uuid',
            ],

            /**
             * The name of the team.
             *
             * @var string
             *
             * @example "HR Department"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * The slug of the team (URL-friendly identifier).
             *
             * @var string
             *
             * @example "hr-department"
             */
            'slug' => ['required',
                'string',
                'max:255',
                // @phpstan-ignore-next-line
                'unique:teams,slug,'.$this->id,
            ],

            /**
             * The type of team.
             *
             * @var string
             *
             * @example "HRD"
             */
            'type' => ['nullable', 'string', 'size:3'],

            /**
             * The creation date of the team.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'created_at' => ['nullable', 'date'],

            /**
             * The last update date of the team.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'updated_at' => ['nullable', 'date'],

            /**
             * The deletion date of the team (for soft deletes).
             *
             * @var string
             *
             * @example "2023-02-01T10:00:00Z"
             */
            'deleted_at' => ['nullable', 'date'],
        ];
    }
}
