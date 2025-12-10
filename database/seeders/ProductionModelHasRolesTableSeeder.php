<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class ProductionModelHasRolesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('model_has_roles')->delete();

        DB::table('model_has_roles')->insert([
            0 => [
                'role_id' => '01990b38-85d0-7100-bfa3-378b872b6b4d',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '019808ed-42c7-7260-ac65-a17bf93bb956',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            1 => [
                'role_id' => '01990b38-84e2-71d6-a0f5-107202922d45',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '019808ed-42c7-7260-ac65-a17bf93bb956',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            2 => [
                'role_id' => '01990b38-85d0-7100-bfa3-378b872b6b4d',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '01980901-474d-7049-8fe2-006626d870ca',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            3 => [
                'role_id' => '01990b38-84e2-71d6-a0f5-107202922d45',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '01980901-474d-7049-8fe2-006626d870ca',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            4 => [
                'role_id' => '01990b38-85d0-7100-bfa3-378b872b6b4d',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '01980925-d908-710f-8c9b-a56ea030c485',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            5 => [
                'role_id' => '01990b38-84e2-71d6-a0f5-107202922d45',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '01980925-d908-710f-8c9b-a56ea030c485',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            6 => [
                'role_id' => '01990b38-85d0-7100-bfa3-378b872b6b4d',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '0198092f-ff0d-707b-bc53-7208b2312281',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            7 => [
                'role_id' => '01990b38-84e2-71d6-a0f5-107202922d45',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '0198092f-ff0d-707b-bc53-7208b2312281',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            8 => [
                'role_id' => '01990b38-85d0-7100-bfa3-378b872b6b4d',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '0198092f-ff0d-707b-bc53-7208b2312282',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
            9 => [
                'role_id' => '01990b38-84e2-71d6-a0f5-107202922d45',
                'model_type' => 'App\\Models\\User',
                'model_uuid' => '0198092f-ff0d-707b-bc53-7208b2312282',
                'team_id' => '66d8d6bb-40a4-34fb-bce4-87bcbb04208b',
            ],
        ]);

    }
}
