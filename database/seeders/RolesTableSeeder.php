<?php

namespace Database\Seeders;

use App\Models\Role;
use DB;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        if (Role::count()) {
            DB::table('roles')->delete();
        }

        DB::table('roles')->insert([
            0 => [
                'id' => '01990b38-84e2-71d6-a0f5-107202922d45',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'god',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            1 => [
                'id' => '01990b38-8517-7063-a50a-e3d8b8b70450',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'hexeko_super_admin',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            2 => [
                'id' => '01990b38-8547-7250-8d45-4e186f571f08',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'hexeko_admin',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            3 => [
                'id' => '01990b38-8573-710f-a2a2-519df53050df',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'division_super_admin',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            4 => [
                'id' => '01990b38-858e-7288-896d-8fd9d3491116',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'division_admin',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            5 => [
                'id' => '01990b38-85a8-7224-a827-1765c5002c5f',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'financer_super_admin',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            6 => [
                'id' => '01990b38-85bf-70a2-90e7-81546ee584cd',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'financer_admin',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
            7 => [
                'id' => '01990b38-85d0-7100-bfa3-378b872b6b4d',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
                'name' => 'beneficiary',
                'guard_name' => 'api',
                'is_protected' => true,
                'created_at' => '2025-09-02 18:17:57',
                'updated_at' => '2025-09-02 18:17:57',
                'deleted_at' => null,
            ],
        ]);

    }
}
