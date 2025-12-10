<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\User;
use App\Models\WorkMode;

class WorkModePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_WORK_MODE);
    }

    public function view(User $user, WorkMode $workMode): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_WORK_MODE)) {
            return $user->current_financer_id === $workMode->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_WORK_MODE);
    }

    public function update(User $user, WorkMode $workMode): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_WORK_MODE)) {
            return $user->current_financer_id === $workMode->financer_id;
        }

        return false;
    }

    public function delete(User $user, WorkMode $workMode): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_WORK_MODE)) {
            return $user->current_financer_id === $workMode->financer_id;
        }

        return false;
    }
}
