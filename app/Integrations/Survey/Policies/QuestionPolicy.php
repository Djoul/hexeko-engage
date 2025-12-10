<?php

namespace App\Integrations\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_QUESTION);
    }

    public function view(User $user, Question $question): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_QUESTION)) {
            return $user->current_financer_id === $question->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_QUESTION);
    }

    public function update(User $user, Question $question): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_QUESTION)) {
            return $user->current_financer_id === $question->financer_id;
        }

        return false;
    }

    public function delete(User $user, Question $question): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_QUESTION)) {
            return $user->current_financer_id === $question->financer_id;
        }

        return false;
    }

    public function restore(User $user, Question $question): bool
    {
        return $this->delete($user, $question);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
