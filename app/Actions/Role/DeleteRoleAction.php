<?php

namespace App\Actions\Role;

use App\Models\Role;
use App\Services\Models\RoleService;
use Exception;
use Log;

class DeleteRoleAction
{
    public function __construct(protected RoleService $roleService) {}

    /**
     * run action
     */
    public function handle(Role $role): bool
    {
        try {
            return $this->roleService->delete($role);
        } catch (Exception $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTrace()]);
            throw $e;
        }
    }
}
