<?php

namespace App\Services;

use App\Enums\IDP\RoleDefaults;
use App\Exceptions\RoleManagement\CannotRemoveBeneficiaryRoleException;
use App\Exceptions\RoleManagement\MaxRolesExceededException;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;

class RoleManagementService
{
    private const MAX_ROLES = 2;

    /**
     * Check if an assigner can manage a specific role
     */
    public function canManageRole(User $assigner, string $targetRole): bool
    {
        // GOD role can manage ALL roles without restriction
        if ($assigner->hasAnyRole(RoleDefaults::GOD)) {
            return true;
        }

        // HEXEKO roles (HEXEKO_SUPER_ADMIN, HEXEKO_ADMIN) have global permissions
        if ($assigner->hasAnyRole([RoleDefaults::HEXEKO_SUPER_ADMIN, RoleDefaults::HEXEKO_ADMIN])) {
            // Use single role for HEXEKO roles
            $assignerRole = $assigner->roles->pluck('name')->first() ?? RoleDefaults::BENEFICIARY;
            $assignerRoles = [$assignerRole];

            return RoleDefaults::canManageRole($assignerRoles, $targetRole);
        }

        // DIVISION roles (DIVISION_SUPER_ADMIN, DIVISION_ADMIN) have division-wide permissions
        if ($assigner->hasAnyRole([RoleDefaults::DIVISION_SUPER_ADMIN, RoleDefaults::DIVISION_ADMIN])) {
            // Use single role for DIVISION roles (they manage all financers in their division)
            $assignerRole = $assigner->roles->pluck('name')->first() ?? RoleDefaults::BENEFICIARY;
            $assignerRoles = [$assignerRole];

            return RoleDefaults::canManageRole($assignerRoles, $targetRole);
        }

        // FINANCER roles are limited to their specific financer context
        // Get the active financer ID
        $activeFinancerId = activeFinancerID($assigner);
        if (in_array($activeFinancerId, [null, '', '0'], true)) {
            // If no active financer, fall back to user's single global role
            $assignerRole = $assigner->roles->pluck('name')->first() ?? RoleDefaults::BENEFICIARY;
            $assignerRoles = [$assignerRole];
        } else {
            // Get roles from the financer_user pivot table for FINANCER_ADMIN and FINANCER_SUPER_ADMIN
            $financerId = is_string($activeFinancerId) ? $activeFinancerId : '';
            $assignerRoles = $this->getUserRolesForFinancer($assigner, $financerId);
        }

        return RoleDefaults::canManageRole($assignerRoles, $targetRole);
    }

    /**
     * Get user role for a specific financer from the pivot table (single role system)
     *
     * @return array<int, string> Single-element array for backward compatibility
     */
    private function getUserRolesForFinancer(User $user, string $financerId): array
    {
        $financerUser = $user->financers()
            ->where('financer_id', $financerId)
            ->first();

        if (! $financerUser || ! $financerUser->pivot) {
            return [RoleDefaults::BENEFICIARY];
        }

        $role = $financerUser->pivot->role ?? RoleDefaults::BENEFICIARY;

        return [$role]; // Return single role as array for backward compatibility
    }

    /**
     * Check if an assigner can transform between two roles
     */
    public function canTransformRole(User $assigner, string $oldRole, string $newRole): bool
    {
        // GOD can transform any role
        if ($assigner->hasAnyRole(RoleDefaults::GOD)) {
            return true;
        }

        return $this->canManageRole($assigner, $oldRole) && $this->canManageRole($assigner, $newRole);
    }

    /**
     * Validate that a user can add another role
     *
     * @throws MaxRolesExceededException
     */
    public function validateCanAddRole(User $user): void
    {
        if ($user->roles->count() >= self::MAX_ROLES) {
            throw new MaxRolesExceededException;
        }
    }

    /**
     * Validate that a role can be removed
     *
     * @throws CannotRemoveBeneficiaryRoleException
     */
    public function validateCanRemoveRole(User $user, string $role): void
    {
        if ($role === RoleDefaults::BENEFICIARY) {
            throw new CannotRemoveBeneficiaryRoleException;
        }
    }

    /**
     * Assign a role to a user with transaction
     */
    public function assignRoleWithTransaction(User $user, string $role): User
    {
        return DB::transaction(function () use ($user, $role) {
            $user->assignRole($role);

            return $user->refresh();
        });
    }

    /**
     * Remove a role from a user with transaction
     */
    public function removeRoleWithTransaction(User $user, string $role): User
    {
        return DB::transaction(function () use ($user, $role) {
            $user->removeRole($role);

            return $user->refresh();
        });
    }

    /**
     * Transform a user's role (remove old, add new) atomically
     */
    public function transformRoleWithTransaction(User $user, string $oldRole, string $newRole): User
    {
        return DB::transaction(function () use ($user, $oldRole, $newRole) {
            $user->removeRole($oldRole);
            $user->assignRole($newRole);

            return $user->refresh();
        });
    }

    /**
     * Get the list of roles that a user can assign based on their current roles
     *
     * @return array<string>
     */
    public function getRolesUserCanAssign(User $user): array
    {
        $userRoles = $user->roles->pluck('name')->toArray();
        // Get all assignable roles for each of the user's roles
        $assignableRoles = [];
        foreach ($userRoles as $role) {
            try {
                $rolesForThisRole = RoleDefaults::getAssignableRoles($role);
                $assignableRoles = array_merge($assignableRoles, $rolesForThisRole);
            } catch (Exception) {
                // Skip roles that don't have assignable roles defined
            }
        }

        // Remove duplicates and return
        return array_unique($assignableRoles);
    }
}
