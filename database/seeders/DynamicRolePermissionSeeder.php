<?php

namespace Database\Seeders;

use App\Enums\IDP\RoleDefaults;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DynamicRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = RoleDefaults::asArray();

        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            $permissionsForRole = RoleDefaults::getPermissionsByRole($roleName);
            $permissionIds = Permission::whereIn('name', $permissionsForRole)
                ->pluck('id')
                ->toArray();

            $role->permissions()->sync($permissionIds);
        }

        // Clear cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
