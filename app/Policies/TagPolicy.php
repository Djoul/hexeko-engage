<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_TAG);
    }

    public function view(User $user, Tag $tag): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::READ_TAG)) {
            return false;
        }

        return $this->canAccessTag($tag);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_TAG);
    }

    public function update(User $user, Tag $tag): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::UPDATE_TAG)) {
            return false;
        }

        return $this->canAccessTag($tag);
    }

    public function delete(User $user, Tag $tag): bool
    {
        if (! $user->hasPermissionTo(PermissionDefaults::DELETE_TAG)) {
            return false;
        }

        return $this->canAccessTag($tag);
    }

    private function canAccessTag(Tag $tag): bool
    {
        $context = authorizationContext();

        if ($tag->financer_id && $context->canAccessFinancer($tag->financer_id)) {
            return true;
        }

        $divisionId = $tag->division?->id;

        return $divisionId !== null && $context->canAccessDivision($divisionId);
    }
}
