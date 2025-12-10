<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * @group Core/Permissions
 *
 * Data validation for permission creation and updating
 */
class PermissionFormRequest extends FormRequest
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
        $isUpdating = request()->routeIs('permissions.update');

        return [
            /**
             * The UUID of the permission (required for updates).
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => [
                new RequiredIf($isUpdating),
                'string',
                'uuid'],

            /**
             * The name of the permission.
             *
             * @var string
             *
             * @example "create_article"
             */
            'name' => [
                'required',
                'string',
                // @phpstan-ignore-next-line
                'unique:permissions,name,'.$this->id,
                'max:255'],

            /**
             * The guard name for the permission.
             *
             * @var string
             *
             * @example "api"
             */
            'guard_name' => ['sometimes', 'string', 'max:3'],

            /**
             * The creation date of the permission.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'created_at' => ['nullable', 'date'],

            /**
             * The last update date of the permission.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'updated_at' => ['nullable', 'date'],
        ];
    }
}
