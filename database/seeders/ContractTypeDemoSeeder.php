<?php

namespace Database\Seeders;

use App\Models\ContractType;
use App\Models\Financer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class ContractTypeDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $contractTypes = [
            [
                'name' => 'Permanent Contract',
            ],
            [
                'name' => 'Fixed-term Contract',
            ],
            [
                'name' => 'Temporary Contract',
            ],
            [
                'name' => 'Internship',
            ],
            [
                'name' => 'Freelance',
            ],
            [
                'name' => 'Apprenticeship',
            ],
        ];

        $contractTypesCount = count($contractTypes);
        $financers = Financer::all();

        foreach ($financers as $financer) {
            for ($i = 0; $i < $contractTypesCount; $i++) {
                ContractType::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $contractTypes[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
