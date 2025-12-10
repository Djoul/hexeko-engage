<?php

namespace App\Actions\Permission;

use App\Models\Permission;
use App\Services\Models\PermissionService;

class UpdatePermissionAction
{
    public function __construct(protected PermissionService $permissionService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(Permission $permission, array $validatedData): Permission
    {
        return $this->permissionService->update($permission, $validatedData);
    }
}
