<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Actions\User\CRUD\DeleteUserAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Services\Models\UserService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

#[Group('User')]
class UserDestroyController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Delete user permanently
     *
     * Permanently delete a user from the system.
     */
    #[RequiresPermission(PermissionDefaults::DELETE_USER)]
    public function __invoke(string $id, DeleteUserAction $deleteUserAction): JsonResponse|Response
    {
        try {
            $user = $this->userService->find($id, ['roles', 'permissions', 'financers']);

            // SECURITY: Verify authenticated user can delete this user (financer isolation)
            $authUser = auth()->user();
            if ($authUser && ! $authUser->can('delete', $user)) {
                abort(403, 'You do not have permission to delete this user');
            }

            $deleteUserAction->handle($user);

            return response()->noContent();
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
