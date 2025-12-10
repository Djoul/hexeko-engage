<?php

namespace App\Actions\User\Roles;

use App\Models\Financer;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class UserSyncRolesAction
{
    /**
     * Execute role assignment for single role system
     *
     * @param  string  $roleName  Single role name to assign
     */
    public function execute(User $user, string $roleName): bool
    {
        // Assign the single role (replaces all existing roles)
        $user->syncRoles([$roleName]);

        // Update the role in financer_user pivot table
        $activeFinancerId = activeFinancerID($user);
        if (is_string($activeFinancerId) && $activeFinancerId !== '' && $activeFinancerId !== '0') {
            $this->updateFinancerUserRole($user, $activeFinancerId, $roleName);
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        return true;
    }

    /**
     * Update role in financer_user pivot table (single role system)
     *
     * Note: Division-level access for DIVISION_ADMIN is handled by UserPolicy::shareDivision(),
     * not by attaching the user to all division financers.
     */
    private function updateFinancerUserRole(User $user, string $financerId, string $role): void
    {
        // Update role for the user's current financer only
        // Division Admin access to other financers is managed via UserPolicy
        $user->financers()->updateExistingPivot($financerId, [
            'role' => $role,
        ]);
    }
}
