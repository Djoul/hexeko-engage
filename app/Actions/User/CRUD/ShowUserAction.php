<?php

declare(strict_types=1);

namespace App\Actions\User\CRUD;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Action to show a single user with relations
 *
 * Retrieves a user by ID with specified eager-loaded relationships.
 * This action extracts the show logic from UserShowController.
 *
 * Security: Enforces financer isolation via UserPolicy to prevent IDOR attacks
 */
class ShowUserAction
{
    /**
     * Default relations to eager load with user
     */
    private const DEFAULT_RELATIONS = ['roles', 'permissions', 'financers', 'departments', 'sites', 'managers', 'contractTypes', 'tags'];

    /**
     * Execute the show user action
     *
     * @param  string  $userId  User UUID to retrieve
     * @param  array<string>  $relations  Relations to eager load (defaults to roles, permissions, financers)
     * @return User User model with loaded relations
     *
     * @throws ModelNotFoundException If user not found
     * @throws AuthorizationException If user lacks permission to view target user
     */
    public function execute(string $userId, array $relations = []): User
    {
        // Use default relations if none specified
        $relationsToLoad = $relations !== [] ? $relations : self::DEFAULT_RELATIONS;

        // Find user with relations using active scope (excludes pending invitations)
        $user = User::with($relationsToLoad)
            ->where('id', $userId)
            ->first();

        if (! $user instanceof User) {
            throw new ModelNotFoundException('User not found');
        }

        // SECURITY: Verify authenticated user can access this user (financer isolation)
        // Only enforce authorization when running in HTTP context with authenticated user
        $authUser = auth()->user();

        if ($authUser instanceof User && ! $authUser->can('view', $user)) {
            throw new AuthorizationException('You do not have permission to view this user');
        }

        return $user;
    }
}
