<?php

namespace App\Actions\User\Roles;

use App\Enums\IDP\RoleDefaults;
use App\Models\Role;
use App\Models\User;
use App\Services\Models\UserService;
use DB;

class UserRevokeRoleAction
{
    public function __construct(protected UserService $userService) {}

    /**
     * Execute role revocation for single role system
     * When a role is revoked, assign BENEFICIARY as default to ensure user always has a role
     */
    public function handle(User $user, Role $role): User
    {
        return DB::transaction(
            function () use ($user, $role): User {
                // Remove the role
                $user = $this->userService->removeRole($user, $role);

                // In single role system, always assign BENEFICIARY as fallback
                // to ensure user always has a role
                if ($user->roles()->count() === 0) {
                    $user->assignRole(RoleDefaults::BENEFICIARY);
                }

                // Update the role in financer_user pivot table
                $activeFinancerId = activeFinancerID($user);
                if (is_string($activeFinancerId) && $activeFinancerId !== '' && $activeFinancerId !== '0') {
                    $this->updateFinancerUserRole($user, $activeFinancerId);
                }

                return $user;
            }
        );
    }

    /**
     * Update role in financer_user pivot table (single role system)
     */
    private function updateFinancerUserRole(User $user, string $financerId): void
    {
        // Get user's single role (first role from Spatie)
        $role = $user->roles()->pluck('name')->first() ?? RoleDefaults::BENEFICIARY;

        // Update the pivot table with single role
        $user->financers()->updateExistingPivot($financerId, [
            'role' => $role,
        ]);
    }
}
