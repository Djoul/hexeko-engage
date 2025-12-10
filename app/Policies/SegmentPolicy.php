<?php

namespace App\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Segment;
use App\Models\User;

class SegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_SEGMENT);
    }

    public function view(User $user, Segment $segment): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_SEGMENT)) {
            return $user->current_financer_id === $segment->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_SEGMENT);
    }

    public function update(User $user, Segment $segment): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_SEGMENT)) {
            return $user->current_financer_id === $segment->financer_id;
        }

        return false;
    }

    public function delete(User $user, Segment $segment): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_SEGMENT)) {
            return $user->current_financer_id === $segment->financer_id;
        }

        return false;
    }

    public function restore(User $user, Segment $segment): bool
    {
        return $this->delete($user, $segment);
    }

    public function forceDelete(User $user, Segment $segment): bool
    {
        return false;
    }
}
