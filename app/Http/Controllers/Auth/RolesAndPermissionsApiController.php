<?php

namespace App\Http\Controllers\Auth;

use App\Enums\IDP\RoleDefaults;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Authentication')]
class RolesAndPermissionsApiController extends Controller
{
    /**
     * Get Roles with assigned permissions
     *
     * Returns a collection of roles with their associated permissions
     */
    public function __invoke(): JsonResponse
    {
        $roles = [
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        $rolesWithPermissions = [];

        foreach ($roles as $role) {
            $rolesWithPermissions[$role] = RoleDefaults::getPermissionsByRole($role);
        }

        return response()->json([
            'data' => $rolesWithPermissions,
        ], Response::HTTP_OK);
    }
}
