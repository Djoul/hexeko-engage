<?php

namespace App\Actions\Permission;

use App\Models\Permission;
use App\Services\Models\PermissionService;

class DeletePermissionAction
{
    public function __construct(protected PermissionService $permissionService) {}

    /**
     * run action
     */
    public function handle(Permission $permission): bool
    {
        return $this->permissionService->delete($permission);
    }
}
