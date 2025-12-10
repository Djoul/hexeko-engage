<?php

namespace App\Http\Controllers\V1\User;

use App\Actions\User\Roles\UserAssigneRoleAction;
use App\Actions\User\Roles\UserRevokeRoleAction;
use App\Actions\User\Roles\UserSyncRolesAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Exceptions\PermissionDeniedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\SyncRolesRequest;
use App\Services\Models\RoleService;
use App\Services\Models\UserService;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Validator;

#[Group('User')]
class UserRolesController extends Controller
{
    /**
     * UserService constructor.
     */
    public function __construct(protected UserService $userService, protected RoleService $roleService) {}

    /**
     * Assign a role to a user
     *
     * Assign a specific role to the user.
     */
    #[RequiresPermission(PermissionDefaults::ASSIGN_ROLES)]
    public function assignRole(string $id, string $role, UserAssigneRoleAction $userAssigneRoleAction)
    {
        $validator = Validator::make(
            ['id' => $id, 'role' => $role],
            [
                'role' => 'required|string|exists:roles,name',
                'id' => 'required|uuid|exists:users,id',
            ]
        );

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->userService->find($id);
            // @phpstan-ignore-next-line
            $role = $this->roleService->findByName($role, $user->team_id);

            return response()->json(['success' => $userAssigneRoleAction->handle($user, $role)]);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (PermissionDeniedException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }

    /**
     * Remove a role from a user
     *
     * Revoke a specific role from the user.
     */
    #[RequiresPermission(PermissionDefaults::REVOKE_ROLES)]
    public function removeRole(string $id, string $role, UserRevokeRoleAction $userRevokeRoleAction)
    {
        $validator = Validator::make(
            ['id' => $id, 'role' => $role],
            [
                'role' => 'required|string|exists:roles,name',
                'id' => 'required|uuid|exists:users,id',
            ]
        );

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = $this->userService->find($id);
            // @phpstan-ignore-next-line
            $role = $this->roleService->findByName($role, $user->team_id);

            if ($role === RoleDefaults::BENEFICIARY) {
                response()->json(
                    [
                        "You can't remove role beneficiary from a user.",
                    ]
                )->setStatusCode(401);
            }

            return response()->json(
                [
                    'success' => $userRevokeRoleAction->handle(
                        $user,
                        $role
                    ),
                ]
            )->setStatusCode(200);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (PermissionDeniedException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sync user roles
     *
     * Synchronize the roles assigned to a user.
     */
    #[RequiresPermission(PermissionDefaults::MANAGE_USER_ROLES)]
    public function syncRoles(string $id, SyncRolesRequest $request, UserSyncRolesAction $userSyncRolesAction)
    {
        try {
            $user = $this->userService->find($id);
            $validatedData = $request->validated();
            $role = $validatedData['role'] ?? '';

            if (! is_string($role) || $role === '') {
                return response()->json(['error' => 'Invalid role format'], 422);
            }

            $success = $userSyncRolesAction->execute($user, $role);

            return response()->json(['success' => $success]);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (PermissionDeniedException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (Exception $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }
}
