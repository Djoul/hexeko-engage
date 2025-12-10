<?php

namespace App\Http\Controllers\V1;

use App\Actions\User\Merge\MergeUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MergeUserRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class MergeUserController extends Controller
{
    public function __construct(
        protected MergeUserAction $mergeUserAction
    ) {}

    /**
     * Merge an invited user with an existing user account
     */
    public function merge(MergeUserRequest $request): JsonResponse
    {
        try {
            // Get the validated data
            $data = $request->validated();

            /** @var string $invitedUserId */
            $invitedUserId = $data['invited_user_id'];
            /** @var string $email */
            $email = $data['email'];

            // Execute merge action
            $mergedUser = $this->mergeUserAction->execute($invitedUserId, $email);

            return response()->json([
                'message' => 'User merged successfully',
                'data' => $mergedUser,
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (Throwable $e) {
            Log::error('Error merging user: '.$e->getMessage(), [
                'trace' => $e->getTrace(),
            ]);

            return response()->json([
                'message' => 'Error merging user',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
