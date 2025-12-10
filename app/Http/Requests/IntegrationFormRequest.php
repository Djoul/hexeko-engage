<?php

namespace App\Http\Requests;

use App\Enums\Integrations\IntegrationTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\RequiredIf;

/**
 * @group Core/Integrations
 *
 * Data validation for integration creation and updating
 */
class IntegrationFormRequest extends FormRequest
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
             * The UUID of the integration (required for updates).
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
             * The UUID of the module this integration belongs to.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'module_id' => ['required', 'string', 'uuid', 'exists:modules,id'],

            /**
             * The name of the integration.
             *
             * @var string
             *
             * @example "InternalCommunication"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * The type of integration.
             *
             * @var string
             *
             * @example "internal"
             */
            'type' => ['required', 'string', 'max:255', 'in:'.implode(',', IntegrationTypes::asArray())],

            /**
             * The description of the integration.
             *
             * @var string
             *
             * @example "Integration for HR communication tools"
             */
            'description' => ['required', 'string'],

            /**
             * The settings for the integration (JSON format).
             *
             * @var array
             *
             * @example {"api_key": "abc123" //pragma: allowlist secret
             * , "endpoint": "https://api.example.com"}
             */
            'settings' => ['sometimes', 'array'],

            /**
             * Whether the integration is active.
             *
             * @var bool
             *
             * @example true
             */
            'active' => ['required', 'boolean'],

            /**
             * The creation date of the integration.
             *
             * @var string
             *
             * @example "2023-01-01T12:00:00Z"
             */
            'created_at' => ['nullable', 'date'],

            /**
             * The last update date of the integration.
             *
             * @var string
             *
             * @example "2023-01-15T14:30:00Z"
             */
            'updated_at' => ['nullable', 'date'],

            /**
             * The deletion date of the integration (for soft deletes).
             *
             * @var string
             *
             * @example "2023-02-01T10:00:00Z"
             */
            'deleted_at' => ['nullable', 'date'],
        ];
    }
}
