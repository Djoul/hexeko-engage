<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for accepting user invitations.
 * Sprint 3 - API Integration: Validates invitation acceptance request data.
 *
 * @group Core/Invitations
 */
class AcceptUserInvitationFormRequest extends FormRequest
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
             * The invitation token.
             *
             * @var string
             *
             * @example "a1b2c3d4e5f6..."
             */
            'token' => ['required', 'string', 'min:40'],

            /**
             * The Cognito user ID.
             *
             * @var string
             *
             * @example "cognito-user-123"
             */
            'cognito_id' => ['required', 'string'],

            /**
             * The user password (optional).
             *
             * @var string|null
             *
             * @example "SecurePassword123!"
             */
            'password' => ['nullable', 'string', 'min:8'],
        ];
    }
}
