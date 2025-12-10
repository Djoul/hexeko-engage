<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_SITE);
    }

    public function view(User $user, Site $site): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_SITE)) {
            return $user->current_financer_id === $site->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_SITE);
    }

    public function update(User $user, Site $site): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_SITE)) {
            return $user->current_financer_id === $site->financer_id;
        }

        return false;
    }

    public function delete(User $user, Site $site): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_SITE)) {
            return $user->current_financer_id === $site->financer_id;
        }

        return false;
    }
}
