<?php

namespace App\Actions\Role;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Models\RoleService;
use DB;
use Log;
use Throwable;

class addPermissionToRoleAction
{
    public function __construct(protected RoleService $roleService) {}

    /**
     * run action
     */
    public function handle(Role $role, Permission $permission): Role
    {

        try {
            return DB::transaction(function () use ($role, $permission): Role {
                return $this->roleService->addPermission($role, $permission);
            });

        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTrace()]);
            throw $e;
        }
    }
}
