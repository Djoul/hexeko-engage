<?php

namespace App\Actions\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnregisterDeviceAction
{
    /**
     * Unregister a device by subscription ID
     */
    public function execute(string $subscriptionId, ?string $userId = null): bool
    {
        return DB::transaction(function () use ($subscriptionId, $userId): bool {
            $query = PushSubscription::where('subscription_id', $subscriptionId);

            if ($userId !== null) {
                $query->where('user_id', $userId);
            }

            $subscription = $query->first();

            if (! $subscription) {
                return false;
            }

            $subscription->delete();

            return true;
        });
    }

    /**
     * Unregister all devices for a specific user
     */
    public function executeForUser(User $user): int
    {
        return DB::transaction(function () use ($user) {
            return PushSubscription::where('user_id', $user->id)->delete();
        });
    }
}
