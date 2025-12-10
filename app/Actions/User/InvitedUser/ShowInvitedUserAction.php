<?php

declare(strict_types=1);

namespace App\Actions\User\InvitedUser;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Action to show a single invited user with metadata
 *
 * Retrieves an invited user by ID with financer, regardless of invitation status
 * This allows handling various invitation states (pending, accepted, expired, revoked)
 * and prepares metadata for the response (available languages, etc.)
 */
class ShowInvitedUserAction
{
    /**
     * Default relations to eager load with invited user
     */
    private const DEFAULT_RELATIONS = ['financers'];

    /**
     * Execute the show invited user action
     *
     * @param  string  $userId  User UUID to retrieve
     * @param  array<string>  $relations  Relations to eager load (defaults to financers)
     * @return array{user: User, financer: Financer|null} User and associated financer
     *
     * @throws ModelNotFoundException If invited user not found
     */
    public function execute(string $userId, array $relations = []): array
    {
        // Use default relations if none specified
        $relationsToLoad = $relations !== [] ? $relations : self::DEFAULT_RELATIONS;

        // Find invited user (any invitation_status: pending, accepted, expired, revoked)
        // Exclude regular users (invitation_status is null)
        $invitedUser = User::with($relationsToLoad)
            ->where('id', $userId)
            ->whereNotNull('invitation_status')
            ->first();

        if (! $invitedUser instanceof User) {
            throw new ModelNotFoundException('Invited user not found');
        }

        // Extract financer from invitation_metadata
        $financerId = $this->extractFinancerIdFromMetadata($invitedUser);
        $financer = in_array($financerId, [null, '', '0'], true) ? null : Financer::find($financerId);
        $invitedUser->financer = $financer;

        return [
            'user' => $invitedUser,
            'financer' => $financer,
        ];
    }

    /**
     * Extract financer ID from invitation metadata
     *
     * @param  User  $invitedUser  Invited user with metadata
     * @return string|null Financer ID or null
     */
    private function extractFinancerIdFromMetadata(User $invitedUser): ?string
    {
        if (! is_array($invitedUser->invitation_metadata)) {
            return null;
        }

        $financerId = $invitedUser->invitation_metadata['financer_id'] ?? null;

        return is_string($financerId) ? $financerId : null;
    }
}
