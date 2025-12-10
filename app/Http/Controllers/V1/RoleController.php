<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Role\addPermissionToRoleAction;
use App\Actions\Role\CreateRoleAction;
use App\Actions\Role\DeleteRoleAction;
use App\Actions\Role\removePermissionFromRoleAction;
use App\Actions\Role\UpdateRoleAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoleFormRequest;
use App\Http\Resources\Role\RoleCollection;
use App\Http\Resources\Role\RoleResource;
use App\Services\Models\PermissionService;
use App\Services\Models\RoleService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;
use Validator;

/**
 * Class RoleController
 */
class RoleController extends Controller
{
    /**
     * RoleService constructor.
     */
    public function __construct(protected RoleService $roleService, protected PermissionService $permissionService) {}

    /**
     * List roles.
     *
     * @response RoleCollection<RoleResource>
     */
    #[RequiresPermission(PermissionDefaults::READ_ROLE)]
    public function index(): RoleCollection
    {
        return new RoleCollection($this->roleService->all());
    }

    /**
     * Show role.
     */
    #[RequiresPermission(PermissionDefaults::READ_ROLE)]
    public function show(string $id): RoleResource
    {
        return new RoleResource($this->roleService->find($id));
    }

    /**
     * Store role.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_ROLE)]
    public function store(RoleFormRequest $request, CreateRoleAction $createRoleAction): RoleResource
    {
        $validatedData = $request->validated();

        $role = $createRoleAction->handle($validatedData);

        return new RoleResource($role);
    }

    /**
     * Update role.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_ROLE)]
    public function update(RoleFormRequest $request, string $id, UpdateRoleAction $updateRoleAction): RoleResource|Response
    {
        $validatedData = $request->validated();

        $role = $this->roleService->find($id);

        $role = $updateRoleAction->handle($role, $validatedData);

        try {
            return new RoleResource($role);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], $e->getCode());
        }
    }

    /**
     * Delete role.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_ROLE)]
    public function destroy(string $id, DeleteRoleAction $deleteRoleAction): Response
    {
        $validator = Validator::make(['id' => $id], ['id' => 'required|uuid|exists:roles,id']);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $this->roleService->find($id);

        try {
            return response()->json(['success' => $deleteRoleAction->handle($role)])->setStatusCode(204);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    }

    /**
     * Add permission to role.
     */
    #[RequiresPermission(PermissionDefaults::ADD_PERMISSION_TO_ROLE)]
    public function addPermissionToRole(string $role, string $permission, addPermissionToRoleAction $addPermissionToRoleAction): JsonResponse
    {
        // validate if role and permission exists
        $validator = Validator::make(['role' => $role, 'permission' => $permission], [
            'role' => 'required|exists:roles,id',
            'permission' => 'required|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $this->roleService->find($role);
        $permission = $this->permissionService->find($permission);

        /* try { */
        return response()->json(
            [
                'success' => $addPermissionToRoleAction->handle(
                    $role,
                    $permission
                ),
            ]
        )->setStatusCode(200);
        /* } catch (\Throwable $e) {
             return response()->json(['error' => $e->getMessage()], $e->getCode());
         }*/
    }

    /**
     * Remove permission from role.
     */
    #[RequiresPermission(PermissionDefaults::REMOVE_PERMISSION_FROM_ROLE)]
    public function removePermissionFromRole(string $role, string $permission, removePermissionFromRoleAction $removePermissionFromRoleAction): JsonResponse
    {

        $validator = Validator::make(['role' => $role, 'permission' => $permission], [
            'role' => 'required|exists:roles,id',
            'permission' => 'required|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $role = $this->roleService->find($role);
        $permission = $this->permissionService->find($permission);

        try {
            return response()->json(
                [
                    'success' => $removePermissionFromRoleAction->handle(
                        $role,
                        $permission
                    ),
                ]
            )->setStatusCode(200);
        } catch (AuthorizationException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (HttpException $e) {
            return response()->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
