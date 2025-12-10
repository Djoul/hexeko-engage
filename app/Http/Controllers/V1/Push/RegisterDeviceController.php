<?php

namespace App\Http\Controllers\V1\Push;

use App\Actions\Push\RegisterDeviceAction;
use App\DTOs\Push\DeviceRegistrationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Push\RegisterDeviceRequest;
use App\Http\Resources\Push\DeviceResource;
use App\Models\PushSubscription;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Notifications/Push')]
class RegisterDeviceController extends Controller
{
    public function __construct(
        private readonly RegisterDeviceAction $registerDeviceAction
    ) {}

    /**
     * Register a new push notification device
     */
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $dto = DeviceRegistrationDTO::from([
            ...$request->validated(),
            'user_id' => $user->id,
        ]);

        $subscription = $this->registerDeviceAction->execute($user, $dto);

        $statusCode = $subscription->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => new DeviceResource($subscription),
        ], $statusCode);
    }

    /**
     * Get user's registered devices
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data' => DeviceResource::collection($subscriptions->items()),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'total' => $subscriptions->total(),
                'per_page' => $subscriptions->perPage(),
                'last_page' => $subscriptions->lastPage(),
            ],
        ]);
    }
}
