<?php

namespace App\Actions\User\Roles;

use App\Models\Role;
use App\Models\User;
use App\Services\Models\UserService;
use DB;

class UserAssigneRoleAction
{
    public function __construct(protected UserService $userService) {}

    /**
     * run action
     */
    public function handle(User $user, Role $role): User
    {
        return DB::transaction(function () use ($user, $role): User {
            // Assign the role using Spatie
            $user = $this->userService->assignRole($user, $role);

            // Update the roles in financer_user pivot table
            $activeFinancerId = activeFinancerID($user);
            if (is_string($activeFinancerId) && $activeFinancerId !== '' && $activeFinancerId !== '0') {
                $this->updateFinancerUserRoles($user, $activeFinancerId);
            }

            return $user;
        });
    }

    /**
     * Update role in financer_user pivot table (single role system)
     */
    private function updateFinancerUserRoles(User $user, string $financerId): void
    {
        // Get user's single role (first role from Spatie)
        $role = $user->roles()->pluck('name')->first() ?? 'beneficiary';

        // Update the pivot table with single role
        $user->financers()->updateExistingPivot($financerId, [
            'role' => $role,
        ]);
    }
}
