<?php

namespace App\Actions\Push;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateSubscriptionAction
{
    /**
     * Update push notification preferences for a device
     */
    public function updatePreferences(string $subscriptionId, array $preferences): ?PushSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $preferences) {
            $subscription = PushSubscription::where('subscription_id', $subscriptionId)->first();

            if (! $subscription) {
                return null;
            }

            $updateData = [
                'last_active_at' => now(),
            ];

            // Update notification settings
            if (isset($preferences['push_enabled'])) {
                $updateData['push_enabled'] = $preferences['push_enabled'];
            }
            if (isset($preferences['sound_enabled'])) {
                $updateData['sound_enabled'] = $preferences['sound_enabled'];
            }
            if (isset($preferences['vibration_enabled'])) {
                $updateData['vibration_enabled'] = $preferences['vibration_enabled'];
            }

            // Update localization settings
            if (isset($preferences['timezone'])) {
                $updateData['timezone'] = $preferences['timezone'];
            }
            if (isset($preferences['language'])) {
                $updateData['language'] = $preferences['language'];
            }

            // Update notification preferences (topics) - merge with existing
            if (isset($preferences['notification_preferences'])) {
                $existingPreferences = $subscription->notification_preferences ?? [];
                $updateData['notification_preferences'] = array_merge(
                    $existingPreferences,
                    $preferences['notification_preferences']
                );
            }

            // Update tags and metadata
            if (isset($preferences['tags'])) {
                $updateData['tags'] = $preferences['tags'];
            }
            if (isset($preferences['metadata'])) {
                $updateData['metadata'] = $preferences['metadata'];
            }

            $subscription->update($updateData);

            return $subscription->fresh();
        });
    }

    /**
     * Update tags for a subscription
     */
    public function updateTags(string $subscriptionId, array $tags): ?PushSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $tags) {
            $subscription = PushSubscription::where('subscription_id', $subscriptionId)->first();

            if (! $subscription) {
                return null;
            }

            $subscription->update([
                'tags' => $tags,
                'last_active_at' => now(),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Update metadata for a subscription
     */
    public function updateMetadata(string $subscriptionId, array $metadata): ?PushSubscription
    {
        return DB::transaction(function () use ($subscriptionId, $metadata) {
            $subscription = PushSubscription::where('subscription_id', $subscriptionId)->first();

            if (! $subscription) {
                return null;
            }

            $subscription->update([
                'metadata' => $metadata,
                'last_active_at' => now(),
            ]);

            return $subscription->fresh();
        });
    }

    /**
     * Update preferences for all user's devices
     */
    public function updatePreferencesForUser(User $user, array $preferences): int
    {
        return DB::transaction(function () use ($user, $preferences): int {
            $subscriptions = PushSubscription::where('user_id', $user->id)->get();
            $updatedCount = 0;

            foreach ($subscriptions as $subscription) {
                $updateData = [
                    'last_active_at' => now(),
                ];

                // Update notification settings
                if (isset($preferences['push_enabled'])) {
                    $updateData['push_enabled'] = $preferences['push_enabled'];
                }
                if (isset($preferences['sound_enabled'])) {
                    $updateData['sound_enabled'] = $preferences['sound_enabled'];
                }
                if (isset($preferences['vibration_enabled'])) {
                    $updateData['vibration_enabled'] = $preferences['vibration_enabled'];
                }

                // Update localization settings
                if (isset($preferences['timezone'])) {
                    $updateData['timezone'] = $preferences['timezone'];
                }
                if (isset($preferences['language'])) {
                    $updateData['language'] = $preferences['language'];
                }

                // Update notification preferences (topics) - merge with existing
                if (isset($preferences['notification_preferences'])) {
                    $existingPreferences = $subscription->notification_preferences ?? [];
                    $updateData['notification_preferences'] = array_merge(
                        $existingPreferences,
                        $preferences['notification_preferences']
                    );
                }

                // Update tags and metadata
                if (isset($preferences['tags'])) {
                    $updateData['tags'] = $preferences['tags'];
                }
                if (isset($preferences['metadata'])) {
                    $updateData['metadata'] = $preferences['metadata'];
                }

                $subscription->update($updateData);
                $updatedCount++;
            }

            return $updatedCount;
        });
    }
}
