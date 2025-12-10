<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\JobTitle;
use App\Models\User;

class JobTitlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_JOB_TITLE);
    }

    public function view(User $user, JobTitle $jobTitle): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_JOB_TITLE)) {
            return $user->current_financer_id === $jobTitle->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_JOB_TITLE);
    }

    public function update(User $user, JobTitle $jobTitle): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_JOB_TITLE)) {
            return $user->current_financer_id === $jobTitle->financer_id;
        }

        return false;
    }

    public function delete(User $user, JobTitle $jobTitle): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_JOB_TITLE)) {
            return $user->current_financer_id === $jobTitle->financer_id;
        }

        return false;
    }
}
