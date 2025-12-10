<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminPanelPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Create GOD role if it doesn't exist
        $godRole = Role::firstOrCreate(
            ['name' => 'GOD'],
            [
                'id' => (string) Str::uuid(),
                'guard_name' => 'api',
            ]
        );

        // Admin Panel Permissions - Three Pillars
        $permissions = [
            // Dashboard Pillar
            'admin.dashboard.view' => 'View Admin Dashboard',
            'admin.dashboard.metrics' => 'View Dashboard Metrics',
            'admin.dashboard.health' => 'View System Health',
            'admin.dashboard.queue' => 'View Queue Status',
            'admin.dashboard.services' => 'View Service Status',

            // Manager Pillar
            'admin.manager.view' => 'View Admin Manager',
            'admin.manager.translations' => 'Manage Translations',
            'admin.manager.migrations' => 'Manage Migrations',
            'admin.migrations.manage' => 'Execute Migrations',
            'admin.roles.manage' => 'Manage Roles and Permissions',
            'admin.audit.view' => 'View Audit Logs',

            // Documentation Pillar
            'admin.docs.view' => 'View Documentation',
            'admin.docs.api' => 'View API Documentation',
            'admin.docs.development' => 'View Development Guides',
        ];

        foreach (array_keys($permissions) as $name) {
            Permission::firstOrCreate(
                ['name' => $name],
                [
                    'id' => (string) Str::uuid(),
                    'guard_name' => 'api',
                ]
            );
        }

        // Give GOD role all permissions
        $godRole->syncPermissions(Permission::all());

        $this->command->info('✅ Admin Panel permissions created successfully');
        $this->command->info('✅ GOD role created with all permissions');
    }
}
