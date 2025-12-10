<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class TestingSeeder extends Seeder
{
    public function run(): void
    {
        // Seed only stable, low-risk data required for tests
        $this->call(DynamicPermissionSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(DynamicRolePermissionSeeder::class);

        // Refresh Spatie permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
