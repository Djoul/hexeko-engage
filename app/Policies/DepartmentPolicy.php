<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_DEPARTMENT);
    }

    public function view(User $user, Department $department): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_DEPARTMENT)) {
            return $user->current_financer_id === $department->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_DEPARTMENT);
    }

    public function update(User $user, Department $department): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_DEPARTMENT)) {
            return $user->current_financer_id === $department->financer_id;
        }

        return false;
    }

    public function delete(User $user, Department $department): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_DEPARTMENT)) {
            return $user->current_financer_id === $department->financer_id;
        }

        return false;
    }
}
