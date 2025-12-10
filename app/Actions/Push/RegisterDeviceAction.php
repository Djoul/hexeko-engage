<?php

namespace App\Actions\Push;

use App\DTOs\Push\DeviceRegistrationDTO;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterDeviceAction
{
    /**
     * Register or update a device subscription
     */
    public function execute(?User $user, DeviceRegistrationDTO $dto): PushSubscription
    {
        return DB::transaction(function () use ($user, $dto) {
            // Check if subscription already exists
            $subscription = PushSubscription::where('subscription_id', $dto->subscriptionId)
                ->first();

            if ($subscription) {
                // Update existing subscription
                $subscription->update([
                    'user_id' => $user?->id,
                    'device_type' => $dto->deviceType->value,
                    'device_model' => $dto->deviceModel,
                    'device_os' => $dto->deviceOs,
                    'app_version' => $dto->appVersion,
                    'push_enabled' => $dto->pushEnabled,
                    'sound_enabled' => $dto->soundEnabled,
                    'vibration_enabled' => $dto->vibrationEnabled,
                    'tags' => $dto->tags,
                    'metadata' => $dto->metadata,
                    'last_active_at' => now(),
                ]);
            } else {
                // Create new subscription
                $subscription = PushSubscription::create([
                    'user_id' => $user?->id,
                    'subscription_id' => $dto->subscriptionId,
                    'device_type' => $dto->deviceType->value,
                    'device_model' => $dto->deviceModel,
                    'device_os' => $dto->deviceOs,
                    'app_version' => $dto->appVersion,
                    'push_enabled' => $dto->pushEnabled,
                    'sound_enabled' => $dto->soundEnabled,
                    'vibration_enabled' => $dto->vibrationEnabled,
                    'tags' => $dto->tags,
                    'metadata' => $dto->metadata,
                    'last_active_at' => now(),
                ]);
            }

            // Return subscription without fresh() to preserve wasRecentlyCreated flag
            return $subscription;
        });
    }
}
