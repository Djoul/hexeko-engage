<?php

namespace Database\Seeders;

use DB;
use Illuminate\Database\Seeder;

class ProductionFinancersTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     */
    public function run(): void
    {

        DB::table('financers')->delete();

        DB::table('financers')->insert([
            0 => [
                'id' => '19780701-9d3a-4b1c-8e4f-32f8d5c90101',
                'name' => 'Hexeko',
                'external_id' => '{"sirh": {"provider": "apideck", "created_at": "2025-10-20T09:32:39+00:00", "created_by": "0199dcb3-524c-730a-9dee-f295c441a88d", "consumer_id": "staging-hexeko-821b71dd"}}',
                'timezone' => 'Europe/Brussels',
                'registration_number' => '5573221438737361',
                'registration_country' => 'BE',
                'website' => 'http://www.koss.com/qui-neque-iure-explicabo-atque-at',
                'iban' => 'BE98811300845361',
                'vat_number' => '8595722259246',
                'representative_id' => null,
                'division_id' => '019904a4-099c-731f-8a18-a65dd25fd7f9',
                'created_at' => '2025-09-01 11:38:03',
                'updated_at' => '2025-09-01 11:38:03',
                'deleted_at' => null,
                'active' => true,
                'available_languages' => '["fr-BE"]',
                'status' => 'active',
                'bic' => 'SOGEFRPP',
                'company_number' => 'BE160809845',
            ],
            1 => [
                'id' => '19780701-a82f-4c9e-91ab-2ddfbc720102',
                'name' => 'Up Portugal',
                'external_id' => null,
                'timezone' => 'Europe/Lisbon',
                'registration_number' => '5490668436715809',
                'registration_country' => 'PT',
                'website' => 'http://feest.com/',
                'iban' => 'FR877441269405N4876Q9L0I676',
                'vat_number' => '6996091088390',
                'representative_id' => null,
                'division_id' => '019904a4-099d-73bd-8c12-35b21bf77cb6',
                'created_at' => '2025-09-01 11:38:04',
                'updated_at' => '2025-09-01 11:38:04',
                'deleted_at' => null,
                'active' => true,
                'available_languages' => '["pt-PT"]',
                'status' => 'active',
                'bic' => 'BNPAFRPPXXX',
                'company_number' => 'FR547197528',
            ],
        ]);

    }
}
