<?php

namespace App\Integrations\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Answer;
use App\Models\User;

class AnswerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_ANSWER);
    }

    public function view(User $user, Answer $answer): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS)) {
            return $user->current_financer_id === $answer->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::READ_ANSWER)) {
            return $user->id === $answer->user_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_ANSWER);
    }

    public function update(User $user, Answer $answer): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS)) {
            return $user->current_financer_id === $answer->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_ANSWER)) {
            return $user->id === $answer->user_id;
        }

        return false;
    }

    public function delete(User $user, Answer $answer): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS)) {
            return $user->current_financer_id === $answer->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::DELETE_ANSWER)) {
            return $user->id === $answer->user_id;
        }

        return false;
    }

    public function complete(User $user, Answer $answer): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_ANSWERS)) {
            return $user->current_financer_id === $answer->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_ANSWER)) {
            return $user->id === $answer->user_id;
        }

        return false;
    }

    public function restore(User $user, Answer $answer): bool
    {
        return $this->delete($user, $answer);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
