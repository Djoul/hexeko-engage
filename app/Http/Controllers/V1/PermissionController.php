<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Permission\CreatePermissionAction;
use App\Actions\Permission\DeletePermissionAction;
use App\Actions\Permission\UpdatePermissionAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionFormRequest;
use App\Http\Resources\Permission\PermissionCollection;
use App\Http\Resources\Permission\PermissionResource;
use App\Services\Models\PermissionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PermissionController
 */
class PermissionController extends Controller
{
    /**
     * PermissionService constructor.
     */
    public function __construct(protected PermissionService $permissionService) {}

    /**
     * List permissions.
     *
     * @response PermissionCollection<PermissionResource>
     */
    #[RequiresPermission(PermissionDefaults::READ_PERMISSION)]
    public function index(): PermissionCollection
    {
        return new PermissionCollection($this->permissionService->all());
    }

    /**
     * Show permission.
     */
    #[RequiresPermission(PermissionDefaults::READ_PERMISSION)]
    public function show(string $id): PermissionResource
    {
        return new PermissionResource($this->permissionService->find($id));
    }

    /**
     * Store permission.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_PERMISSION)]
    public function store(
        PermissionFormRequest $request,
        CreatePermissionAction $createPermissionAction
    ): PermissionResource {
        $validatedData = $request->validated();

        $permission = $createPermissionAction->handle($validatedData);

        return new PermissionResource($permission);
    }

    /**
     * Update permission.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_PERMISSION)]
    public function update(PermissionFormRequest $request, string $id, UpdatePermissionAction $updatePermissionAction): PermissionResource
    {
        $validatedData = $request->validated();

        $permission = $this->permissionService->find($id);

        $permission = $updatePermissionAction->handle($permission, $validatedData);

        return new PermissionResource($permission);
    }

    /**
     * Delete permission.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_PERMISSION)]
    public function destroy(string $id, DeletePermissionAction $deletePermissionAction): Response
    {
        $permission = $this->permissionService->find($id);

        return response()->json(
            ['success' => $deletePermissionAction->handle($permission)]
        )->setStatusCode(204);
    }
}
