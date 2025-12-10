<?php

namespace App\Integrations\InternalCommunication\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\User;

class TagPolicy
{
    /**
     * Determine whether the user can view any tags.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function viewAny(User $user): bool
    {

        return $user->hasPermissionTo(PermissionDefaults::READ_TAG);
    }

    /**
     * Determine whether the user can view the tag.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function view(User $user, Tag $tag): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_TAG)) {
            return $user->current_financer_id === $tag->financer_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create tags.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_TAG);
    }

    /**
     * Determine whether the user can update the tag.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function update(User $user, Tag $tag): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_ARTICLE)) {
            return $user->current_financer_id === $tag->financer_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the tag.
     *
     * Note: financer_id filtering is handled automatically by HasFinancer global scope.
     */
    public function delete(User $user, Tag $tag): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_TAG)) {
            return $user->current_financer_id === $tag->financer_id;
        }

        return false;
    }
}
