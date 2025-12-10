<?php

namespace App\Actions\Role;

use App\Models\Role;
use App\Services\Models\RoleService;

class CreateRoleAction
{
    public function __construct(protected RoleService $roleService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Role
    {
        return $this->roleService->create($validatedData);
    }
}
