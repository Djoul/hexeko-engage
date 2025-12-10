<?php

namespace App\Actions\Permission;

use App\Models\Permission;
use App\Services\Models\PermissionService;

class CreatePermissionAction
{
    public function __construct(protected PermissionService $permissionService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Permission
    {
        return $this->permissionService->create($validatedData);
    }
}
