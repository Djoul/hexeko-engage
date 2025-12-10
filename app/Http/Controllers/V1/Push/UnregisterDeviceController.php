<?php

namespace App\Http\Controllers\V1\Push;

use App\Actions\Push\UnregisterDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Push\UnregisterDeviceRequest;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Notifications/Push')]
class UnregisterDeviceController extends Controller
{
    public function __construct(
        private readonly UnregisterDeviceAction $unregisterDeviceAction
    ) {}

    /**
     * Unregister a specific device by subscription ID
     */
    public function destroy(string $subscriptionId): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Handle special cases
        if ($subscriptionId === 'all') {
            return $this->unregisterAllDevices();
        }

        $result = $this->unregisterDeviceAction->execute($subscriptionId, $user->id);

        if (! $result) {
            return response()->json([
                'message' => 'Device not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'success' => true,
                'message' => 'Device unregistered successfully',
            ],
        ], 200);
    }

    /**
     * Batch unregister devices
     */
    public function batchUnregister(UnregisterDeviceRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $subscriptionIds = $request->validated()['subscription_ids'];

        $unregisteredCount = 0;

        foreach ($subscriptionIds as $subscriptionId) {
            $result = $this->unregisterDeviceAction->execute($subscriptionId, $user->id);
            if ($result) {
                $unregisteredCount++;
            }
        }

        return response()->json([
            'data' => [
                'success' => true,
                'message' => "{$unregisteredCount} devices unregistered successfully",
                'count' => $unregisteredCount,
            ],
        ]);
    }

    /**
     * Unregister all devices for a user
     */
    private function unregisterAllDevices(): JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        $unregisteredCount = $this->unregisterDeviceAction->executeForUser($user);

        return response()->json([
            'data' => [
                'success' => true,
                'message' => "{$unregisteredCount} devices unregistered successfully",
                'count' => $unregisteredCount,
            ],
        ]);
    }
}
