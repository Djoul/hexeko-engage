<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for revoking user invitations.
 * Sprint 3 - API Integration: Validates invitation revocation request data.
 *
 * @group Core/Invitations
 */
class RevokeUserInvitationFormRequest extends FormRequest
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
             * The user ID to revoke invitation for.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'user_id' => ['required', 'string', 'uuid'],

            /**
             * The reason for revoking the invitation (optional).
             *
             * @var string|null
             *
             * @example "Position filled"
             */
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
