<?php

declare(strict_types=1);

namespace App\Actions\User\InvitedUser;

use App\DTOs\User\RevokeUserInvitationDTO;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Action for revoking a user invitation.
 * Sprint 2 - Actions: Orchestrates invitation revocation with validation and state transitions.
 */
class RevokeUserInvitationAction
{
    /**
     * Execute the invitation revocation.
     *
     * @throws Exception if user not found, already accepted, or already revoked
     */
    public function execute(RevokeUserInvitationDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            // Find user by ID
            $user = User::find($dto->userId);

            if (! $user) {
                throw new Exception('User not found');
            }

            // Validate invitation state
            $this->validateInvitationState($user);

            // Validate state transition
            if (! $user->canTransitionTo('revoked')) {
                throw new Exception('Invalid state transition');
            }

            // Merge existing metadata with revocation data
            $existingMetadata = $user->invitation_metadata ?? [];
            $revocationData = $dto->toArray();
            $revocationData['invitation_metadata'] = array_merge(
                $existingMetadata,
                $revocationData['invitation_metadata']
            );

            // Update user with revocation data
            $user->update($revocationData);

            // Refresh to load updated data
            $user->refresh();

            return $user;
        });
    }

    /**
     * Validate the invitation state before revocation.
     *
     * @throws Exception if invitation is already accepted or revoked
     */
    private function validateInvitationState(User $user): void
    {
        // Check if already accepted
        if ($user->invitation_status === 'accepted') {
            throw new Exception('Cannot revoke accepted invitation');
        }

        // Check if already revoked
        if ($user->invitation_status === 'revoked') {
            throw new Exception('Invitation already revoked');
        }
    }
}
