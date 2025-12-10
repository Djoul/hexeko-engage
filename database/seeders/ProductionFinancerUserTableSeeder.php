<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class ProductionFinancerUserTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('financer_user')->delete();

        DB::table('financer_user')->insert([
            0 => [
                'id' => '0199098d-08ac-7081-9156-4a51d1b13b2b',
                'financer_id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'user_id' => '019808ed-42c7-7260-ac65-a17bf93bb956',
                'active' => true,
                'sirh_id' => null,
                'created_at' => '2025-09-02 10:31:01',
                'updated_at' => '2025-09-02 10:31:01',
                'from' => '2025-09-02 10:31:01',
                'to' => null,
                'role' => 'god',
            ],
            1 => [
                'id' => '0199098d-08d0-707e-8186-12b479c5690a',
                'financer_id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'user_id' => '01980925-d908-710f-8c9b-a56ea030c485',
                'active' => true,
                'sirh_id' => null,
                'created_at' => '2025-09-02 10:31:01',
                'updated_at' => '2025-09-02 10:31:01',
                'from' => '2025-09-02 10:31:01',
                'to' => null,
                'role' => 'god',
            ],
            2 => [
                'id' => '0199098d-08f1-7139-969d-24a0de81b773',
                'financer_id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'user_id' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
                'active' => true,
                'sirh_id' => null,
                'created_at' => '2025-09-02 10:31:01',
                'updated_at' => '2025-09-02 10:31:01',
                'from' => '2025-09-02 10:31:01',
                'to' => null,
                'role' => 'god',
            ],
            3 => [
                'id' => '0199098d-08bf-717c-b80f-7a80fb5de4b0',
                'financer_id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'user_id' => '01980901-474d-7049-8fe2-006626d870ca',
                'active' => true,
                'sirh_id' => null,
                'created_at' => '2025-09-02 10:31:01',
                'updated_at' => '2025-09-02 10:31:01',
                'from' => '2025-09-02 10:31:01',
                'to' => null,
                'role' => 'god',
            ],
            4 => [
                'id' => '0199098d-08e0-7074-aa20-163f1d57be33',
                'financer_id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'user_id' => '0198092f-ff0d-707b-bc53-7208b2312281',
                'active' => true,
                'sirh_id' => null,
                'created_at' => '2025-09-02 10:31:01',
                'updated_at' => '2025-09-02 10:31:01',
                'from' => '2025-09-02 10:31:01',
                'to' => null,
                'role' => 'god',
            ],
            5 => [
                'id' => '0199098d-08e0-7074-aa20-163f1d57be34',
                'financer_id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'user_id' => '0198092f-ff0d-707b-bc53-7208b2312282',
                'active' => true,
                'sirh_id' => null,
                'created_at' => '2025-09-02 10:31:01',
                'updated_at' => '2025-09-02 10:31:01',
                'from' => '2025-09-02 10:31:01',
                'to' => null,
                'role' => 'god',
            ],
        ]);

    }
}
