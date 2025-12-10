<?php

namespace Database\Seeders;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DynamicPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = PermissionDefaults::asArray();

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'id' => Str::uuid()->toString(),
                    'guard_name' => 'api',
                    'is_protected' => true,
                ]
            );
        }
    }
}
