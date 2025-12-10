<?php

namespace App\Integrations\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Theme;
use App\Models\User;

class ThemePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_THEME);
    }

    public function view(User $user, Theme $theme): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_THEME)) {
            return $user->current_financer_id === $theme->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_THEME);
    }

    public function update(User $user, Theme $theme): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_THEME)) {
            return $user->current_financer_id === $theme->financer_id;
        }

        return false;
    }

    public function delete(User $user, Theme $theme): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_THEME)) {
            return $user->current_financer_id === $theme->financer_id;
        }

        return false;
    }

    public function restore(User $user, Theme $theme): bool
    {
        return $this->delete($user, $theme);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
