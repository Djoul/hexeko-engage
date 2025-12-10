<?php

declare(strict_types=1);

namespace App\DTOs\User;

use Carbon\Carbon;

/**
 * DTO for accepting a user invitation.
 * Sprint 2 - Actions: Type-safe data transfer for invitation acceptance.
 */
final readonly class AcceptUserInvitationDTO
{
    public function __construct(
        public string $token,
        public string $cognitoId,
        public ?string $password = null,
    ) {}

    /**
     * Create DTO from array (e.g., validated request data).
     *
     * @param  array{
     *     token: string,
     *     cognito_id: string,
     *     password?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            cognitoId: $data['cognito_id'],
            password: $data['password'] ?? null,
        );
    }

    /**
     * Convert DTO to array for user update.
     *
     * @return array{invitation_status: string, invitation_accepted_at: Carbon, invitation_token: null, cognito_id: string, enabled: bool}
     */
    public function toArray(): array
    {
        return [
            'invitation_status' => 'accepted',
            'invitation_accepted_at' => now(),
            'invitation_token' => null, // Clear token after acceptance
            'cognito_id' => $this->cognitoId,
            'enabled' => true,
        ];
    }
}
