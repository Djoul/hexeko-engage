<?php

namespace App\Http\Requests;

use App\Enums\ModulesCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * @group Core/Modules
 *
 * Data validation for module creation and updating
 */
class ModuleFormRequest extends FormRequest
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
             * The UUID of the module (required for updates).
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => [// required if Update
                new RequiredIf(request()->routeIs('modules.update')),
                'string',
                'uuid',
            ],

            /**
             * The name of the module.
             *
             * @var string
             *
             * @example "HR Management"
             */
            'name' => ['required', 'array', 'max:255'],

            /**
             * The description of the module.
             *
             * @var string
             *
             * @example "Module for managing HR resources and communications"
             */
            'description' => ['required', 'array'],

            /**
             * The category of the module.
             *
             * @var string
             *
             * @example "purchasing_power"
             */
            'category' => ['required', 'string', 'in:'.implode(',', ModulesCategories::getValues())],

            /**
             * The settings for the module (JSON format).
             *
             * @var array
             *
             * @example {"default_view": "dashboard", "notifications": true}
             */
            'settings' => ['sometimes', 'nullable', 'array'],

            /**
             * Whether the module is active.
             *
             * @var bool
             *
             * @example true
             */
            'active' => ['sometimes', 'boolean'],

            /**
             * The creation date of the module.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'created_at' => ['sometimes', 'nullable', 'date'],

            /**
             * The last update date of the module.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'updated_at' => ['sometimes', 'nullable', 'date'],

            /**
             * The deletion date of the module (for soft deletes).
             *
             * @var string
             *
             * @example "2023-02-01T10:00:00Z"
             */
            'deleted_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
