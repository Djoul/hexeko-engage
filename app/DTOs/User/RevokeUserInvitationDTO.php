<?php

declare(strict_types=1);

namespace App\DTOs\User;

/**
 * DTO for revoking a user invitation.
 * Sprint 2 - Actions: Type-safe data transfer for invitation revocation.
 */
final readonly class RevokeUserInvitationDTO
{
    public function __construct(
        public string $userId,
        public string $revokedBy,
        public ?string $reason = null,
    ) {}

    /**
     * Create DTO from array (e.g., validated request data).
     *
     * @param  array{
     *     user_id: string,
     *     revoked_by: string,
     *     reason?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            revokedBy: $data['revoked_by'],
            reason: $data['reason'] ?? null,
        );
    }

    /**
     * Convert DTO to array for user update.
     *
     * @return array{
     *     invitation_status: string,
     *     invitation_token: null,
     *     invitation_metadata: array
     * }
     */
    public function toArray(): array
    {
        $metadata = [
            'revoked_at' => now()->toIso8601String(),
            'revoked_by' => $this->revokedBy,
        ];

        if (! in_array($this->reason, [null, '', '0'], true)) {
            $metadata['revoked_reason'] = $this->reason;
        }

        return [
            'invitation_status' => 'revoked',
            'invitation_token' => null, // Clear token after revocation
            'invitation_metadata' => $metadata,
        ];
    }
}
