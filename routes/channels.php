<?php

use Illuminate\Support\Facades\Broadcast;

// Channel authorization callbacks are always registered so that private/presence
// channel authentication works in all environments (including tests), regardless
// of the configured broadcasting driver.

Broadcast::channel('App.Models.User.{id}', function ($user, $id): bool {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('user.{userId}', function ($user, $userId): bool {
    return (string) $user->id === (string) $userId;
});

Broadcast::channel('financer.{financerId}', function ($user, $financerId): bool {
    // Check if user has access to this financer
    // You may need to adjust this based on your authorization logic
    return (string) $user->financer_id === (string) $financerId || (bool) $user->hasRole('admin');
});

Broadcast::channel('division.{divisionId}', function ($user, $divisionId): bool {
    // Check if user has access to this division
    // You may need to adjust this based on your authorization logic
    if (method_exists($user, 'divisions') && $user->divisions->contains('id', $divisionId)) {
        return true;
    }

    return (bool) $user->hasRole('admin');
});

Broadcast::channel('team.{teamId}', function ($user, $teamId): bool {
    // Check if user belongs to this team
    if (method_exists($user, 'teams') && $user->teams->contains('id', $teamId)) {
        return true;
    }

    return (bool) $user->hasRole('admin');
});
