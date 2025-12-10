<?php

namespace App\Policies;

use App\Models\NotificationTopic;
use App\Models\User;

class NotificationTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, NotificationTopic $topic): bool
    {
        return $this->canAccessTopic($topic);
    }

    public function subscribe(User $user, NotificationTopic $topic): bool
    {
        return $topic->is_active && $this->canAccessTopic($topic);
    }

    public function unsubscribe(User $user, NotificationTopic $topic): bool
    {
        return $this->canAccessTopic($topic);
    }

    private function canAccessTopic(NotificationTopic $topic): bool
    {
        if ($topic->financer_id === null) {
            return true;
        }

        if (authorizationContext()->canAccessFinancer($topic->financer_id)) {
            return true;
        }

        $divisionId = $topic->division?->id;

        return $divisionId !== null && authorizationContext()->canAccessDivision($divisionId);
    }
}
