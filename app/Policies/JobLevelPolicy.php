<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\JobLevel;
use App\Models\User;

class JobLevelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_JOB_LEVEL);
    }

    public function view(User $user, JobLevel $jobLevel): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_JOB_LEVEL)) {
            return $user->current_financer_id === $jobLevel->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_JOB_LEVEL);
    }

    public function update(User $user, JobLevel $jobLevel): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_JOB_LEVEL)) {
            return $user->current_financer_id === $jobLevel->financer_id;
        }

        return false;
    }

    public function delete(User $user, JobLevel $jobLevel): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_JOB_LEVEL)) {
            return $user->current_financer_id === $jobLevel->financer_id;
        }

        return false;
    }
}
