<?php

namespace App\Integrations\HRTools\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\HRTools\Models\Link;
use App\Models\User;

class LinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_HRTOOLS);
    }

    public function view(User $user, Link $link): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::READ_HRTOOLS)) {
            return false;
        }

        return $this->canAccessLink($link);
    }

    public function create(User $user, ?string $financerId = null): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::CREATE_HRTOOLS)) {
            return false;
        }

        return $financerId === null
            || authorizationContext()->canAccessFinancer($financerId);
    }

    public function update(User $user, Link $link): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::UPDATE_HRTOOLS)) {
            return false;
        }

        return $this->canAccessLink($link);
    }

    public function delete(User $user, Link $link): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::DELETE_HRTOOLS)) {
            return false;
        }

        return $this->canAccessLink($link);
    }

    private function canAccessLink(Link $link): bool
    {
        if ($link->financer_id === null) {
            return true;
        }

        if (authorizationContext()->canAccessFinancer($link->financer_id)) {
            return true;
        }

        $divisionId = $link->division?->id;

        return $divisionId !== null && authorizationContext()->canAccessDivision($divisionId);
    }
}
