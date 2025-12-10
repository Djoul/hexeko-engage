<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LogoutUserAction;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Authentication')]
class LogoutApiController extends Controller
{
    public function __construct(protected LogoutUserAction $logoutUserAction) {}

    /**
     * Logout user
     */
    public function __invoke(Request $request): JsonResponse
    {
        $accessToken = $request->bearerToken();

        if (! $accessToken) {
            return response()->json(['error' => 'Access token is required.'], 401);
        }

        $result = $this->logoutUserAction->handle($accessToken);

        if (array_key_exists('error', $result)) {
            return response()->json(['error' => $result['error']], 400);
        }

        return response()->json(['message' => $result['message']]);
    }
}
