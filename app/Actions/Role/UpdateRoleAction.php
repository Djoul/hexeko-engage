<?php

namespace App\Actions\Role;

use App\Models\Role;
use App\Services\Models\RoleService;

class UpdateRoleAction
{
    public function __construct(protected RoleService $roleService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(Role $role, array $validatedData): Role
    {
        return $this->roleService->update($role, $validatedData);
    }
}
