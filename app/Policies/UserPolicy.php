<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\IDP\RoleDefaults;
use App\Models\User;

/**
 * Authorization policy for User model
 *
 * Ensures financer isolation for multi-tenant security.
 * Prevents IDOR (Insecure Direct Object Reference) vulnerabilities
 * by verifying users can only access data from their own financer(s).
 */
class UserPolicy
{
    /**
     * Determine if the authenticated user can view the target user
     *
     * Access is granted if:
     * - Auth user has GOD role (bypasses all restrictions)
     * - Auth user is viewing their own profile
     * - Auth user has DIVISION_ADMIN role and shares division with target user
     * - Target user belongs to at least one financer shared with auth user (active attachment)
     *
     * @param  User  $authUser  The authenticated user
     * @param  User  $targetUser  The user being accessed
     * @return bool True if access is allowed, false otherwise
     */
    public function view(User $authUser, User $targetUser): bool
    {
        // GOD role bypasses all financer restrictions
        if ($authUser->hasRole(RoleDefaults::GOD)) {
            return true;
        }

        // Users can always view their own profile
        if ($authUser->id === $targetUser->id) {
            return true;
        }

        // DIVISION_ADMIN: check division-level isolation
        if ($authUser->hasRole(RoleDefaults::DIVISION_ADMIN)) {
            return $this->shareDivision($authUser, $targetUser);
        }

        // Other roles: financer-level isolation
        return $this->shareActiveFinancer($authUser, $targetUser);
    }

    /**
     * Determine if the authenticated user can update the target user
     *
     * Same logic as view() - users can only update users from their financer(s)
     */
    public function update(User $authUser, User $targetUser): bool
    {
        return $this->view($authUser, $targetUser);
    }

    /**
     * Determine if the authenticated user can delete the target user
     *
     * Same logic as view() - users can only delete users from their financer(s)
     */
    public function delete(User $authUser, User $targetUser): bool
    {
        return $this->view($authUser, $targetUser);
    }

    /**
     * Check if two users share at least one active financer
     *
     * This is the core multi-tenant isolation check.
     * Only returns true if BOTH users have an ACTIVE attachment to the same financer.
     *
     * @param  User  $authUser  The authenticated user
     * @param  User  $targetUser  The user being accessed
     * @return bool True if users share at least one active financer
     */
    private function shareActiveFinancer(User $authUser, User $targetUser): bool
    {
        // Get all active financer IDs for auth user
        $authUserFinancerIds = $authUser->financers()
            ->wherePivot('active', true)
            ->pluck('financers.id')
            ->toArray();

        // Check if target user has active attachment to any of those financers
        return $targetUser->financers()
            ->wherePivot('active', true)
            ->whereIn('financers.id', $authUserFinancerIds)
            ->exists();
    }

    /**
     * Check if two users share at least one division
     *
     * This is used for DIVISION_ADMIN role to enforce division-level isolation.
     * Returns true if target user has at least one active financer
     * that belongs to the same division as any of auth user's active financers.
     *
     * @param  User  $authUser  The authenticated user (must have DIVISION_ADMIN role)
     * @param  User  $targetUser  The user being accessed
     * @return bool True if users share at least one division via their financers
     */
    private function shareDivision(User $authUser, User $targetUser): bool
    {
        // Get all division IDs from auth user's active financers
        $authUserDivisionIds = $authUser->financers()
            ->wherePivot('active', true)
            ->pluck('financers.division_id')
            ->unique()
            ->toArray();

        // If auth user has no divisions, deny access
        if ($authUserDivisionIds === []) {
            return false;
        }

        // Check if target user has at least one active financer in those divisions
        return $targetUser->financers()
            ->wherePivot('active', true)
            ->whereIn('financers.division_id', $authUserDivisionIds)
            ->exists();
    }
}
