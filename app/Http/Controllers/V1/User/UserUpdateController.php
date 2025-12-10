<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Actions\User\CRUD\UpdateUserAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserFormRequest;
use App\Http\Resources\User\UserResource;
use App\Services\Models\UserService;
use Dedoc\Scramble\Attributes\Group;

#[Group('User')]
class UserUpdateController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Update user
     *
     * Update an existing user's information.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_USER, PermissionDefaults::SELF_UPDATE_USER])]
    public function __invoke(UserFormRequest $request, string $id, UpdateUserAction $updateUserAction): UserResource
    {
        $validatedData = $request->validated();

        $authUser = auth()->user();
        if ($authUser &&
            ! $authUser->can(PermissionDefaults::UPDATE_USER) &&
            $authUser->can(PermissionDefaults::SELF_UPDATE_USER) &&
            $authUser->id !== $id) {
            abort(403, 'You can only update your own profile');
        }

        $user = $this->userService->find($id, ['roles', 'permissions', 'financers']);

        // SECURITY: Verify authenticated user can update this user (financer isolation)
        if ($authUser && ! $authUser->can('update', $user)) {
            abort(403, 'You do not have permission to update this user');
        }

        $user = $updateUserAction->handle($user, $validatedData);

        $user->load(['roles', 'permissions', 'financers', 'departments', 'sites', 'managers', 'contractTypes', 'tags']);

        return new UserResource($user);
    }
}
