<?php

declare(strict_types=1);

namespace App\Actions\User\InvitedUser;

use App\DTOs\User\AcceptUserInvitationDTO;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Action for accepting a user invitation.
 * Sprint 2 - Actions: Orchestrates invitation acceptance with validation and state transitions.
 */
class AcceptUserInvitationAction
{
    /**
     * Execute the invitation acceptance.
     *
     * @throws Exception if token invalid, expired, already accepted, or revoked
     */
    public function execute(AcceptUserInvitationDTO $dto): User
    {
        return DB::transaction(function () use ($dto): User {
            // Find user by invitation token
            $user = User::where('invitation_token', $dto->token)->first();

            if (! $user) {
                throw new Exception('Invalid invitation token');
            }

            // Validate invitation state
            $this->validateInvitationState($user);

            // Validate state transition
            if (! $user->canTransitionTo('accepted')) {
                throw new Exception('Invalid state transition');
            }

            // Update user with acceptance data
            $user->update($dto->toArray());

            // Refresh to load updated data
            $user->refresh();

            return $user;
        });
    }

    /**
     * Validate the invitation state before acceptance.
     *
     * @throws Exception if invitation is expired, already accepted, or revoked
     */
    private function validateInvitationState(User $user): void
    {
        // Check if already accepted
        if ($user->invitation_status === 'accepted') {
            throw new Exception('Invitation already accepted');
        }

        // Check if revoked
        if ($user->invitation_status === 'revoked') {
            throw new Exception('Invitation has been revoked');
        }

        // Check if expired
        if ($user->isInvitationExpired()) {
            throw new Exception('Invitation has expired');
        }
    }
}
