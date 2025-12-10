<?php

namespace App\Integrations\Survey\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Integrations\Survey\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::READ_SUBMISSION);
    }

    public function view(User $user, Submission $submission): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS)) {
            return $user->current_financer_id === $submission->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::READ_SUBMISSION)) {
            return $user->id === $submission->user_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissionDefaults::CREATE_SUBMISSION);
    }

    public function update(User $user, Submission $submission): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS)) {
            return $user->current_financer_id === $submission->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_SUBMISSION)) {
            return $user->id === $submission->user_id;
        }

        return false;
    }

    public function delete(User $user, Submission $submission): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS)) {
            return $user->current_financer_id === $submission->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::DELETE_SUBMISSION)) {
            return $user->id === $submission->user_id;
        }

        return false;
    }

    public function complete(User $user, Submission $submission): bool
    {
        if ($user->hasPermissionTo(PermissionDefaults::MANAGE_FINANCER_SUBMISSIONS)) {
            return $user->current_financer_id === $submission->financer_id;
        }

        if ($user->hasPermissionTo(PermissionDefaults::UPDATE_SUBMISSION)) {
            return $user->id === $submission->user_id;
        }

        return false;
    }

    public function restore(User $user, Submission $submission): bool
    {
        return $this->delete($user, $submission);
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }
}
