<?php

namespace App\Integrations\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Questionnaire;
use App\Models\User;

class QuestionnairePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_QUESTIONNAIRE);
    }

    public function view(User $user, Questionnaire $questionnaire): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_QUESTIONNAIRE)) {
            return $user->current_financer_id === $questionnaire->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_QUESTIONNAIRE);
    }

    public function update(User $user, Questionnaire $questionnaire): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_QUESTIONNAIRE)) {
            return $user->current_financer_id === $questionnaire->financer_id;
        }

        return false;
    }

    public function delete(User $user, Questionnaire $questionnaire): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_QUESTIONNAIRE)) {
            return $user->current_financer_id === $questionnaire->financer_id;
        }

        return false;
    }

    public function restore(User $user, Questionnaire $questionnaire): bool
    {
        return $this->delete($user, $questionnaire);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
