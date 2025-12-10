<?php

namespace App\Http\Controllers\V1\Push;

use App\Actions\Push\UpdateSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Push\UpdatePreferencesRequest;
use App\Http\Resources\Push\DeviceResource;
use App\Models\PushSubscription;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Notifications/Push')]
class UpdatePreferencesController extends Controller
{
    public function __construct(
        private readonly UpdateSubscriptionAction $updateSubscriptionAction
    ) {}

    /**
     * Update preferences for all user devices or specific device
     */
    public function update(UpdatePreferencesRequest $request, ?string $subscriptionId = null): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userId = $user->id;
        $preferences = $request->validated();

        if (! in_array($subscriptionId, [null, '', '0'], true)) {
            return $this->updateSpecificDevice($subscriptionId, $preferences, $userId);
        }

        return $this->updateAllUserDevices($preferences, $userId);
    }

    /**
     * Get current preferences for a specific device
     */
    public function show(string $subscriptionId): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userId = $user->id;

        $subscription = PushSubscription::where('subscription_id', $subscriptionId)
            ->where('user_id', $userId)
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'Device not found',
            ], 404);
        }

        return response()->json([
            'data' => new DeviceResource($subscription),
        ]);
    }

    /**
     * Update preferences for a specific device
     */
    private function updateSpecificDevice(string $subscriptionId, array $preferences, string $userId): JsonResponse
    {
        $subscription = PushSubscription::where('subscription_id', $subscriptionId)
            ->where('user_id', $userId)
            ->first();

        if (! $subscription) {
            return response()->json([
                'message' => 'Device not found',
            ], 404);
        }

        $updatedSubscription = $this->updateSubscriptionAction->updatePreferences($subscriptionId, $preferences);

        return response()->json([
            'data' => new DeviceResource($updatedSubscription),
        ]);
    }

    /**
     * Update preferences for all user devices
     */
    private function updateAllUserDevices(array $preferences, string $userId): JsonResponse
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        $updatedCount = 0;

        foreach ($subscriptions as $subscription) {
            $result = $this->updateSubscriptionAction->updatePreferences(
                $subscription->subscription_id,
                $preferences
            );

            if ($result instanceof PushSubscription) {
                $updatedCount++;
            }
        }

        return response()->json([
            'data' => [
                'success' => true,
                'message' => 'Preferences updated successfully',
                'updated_devices' => $updatedCount,
            ],
        ]);
    }
}
