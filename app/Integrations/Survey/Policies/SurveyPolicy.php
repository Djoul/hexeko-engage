<?php

namespace App\Integrations\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Survey;
use App\Models\User;

class SurveyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_SURVEY);
    }

    public function view(User $user, Survey $survey): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::READ_SURVEY)) {
            return $user->current_financer_id === $survey->financer_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_SURVEY);
    }

    public function update(User $user, Survey $survey): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_SURVEY)) {
            return $user->current_financer_id === $survey->financer_id;
        }

        return false;
    }

    public function delete(User $user, Survey $survey): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::DELETE_SURVEY)) {
            return $user->current_financer_id === $survey->financer_id;
        }

        return false;
    }

    public function restore(User $user, Survey $survey): bool
    {
        return $this->delete($user, $survey);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
