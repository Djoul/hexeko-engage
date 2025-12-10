<?php

namespace Database\Seeders;

use App\Models\Financer;
use App\Models\Site;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class SiteDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $sites = [
            [
                'name' => 'Headquarters',
            ],
            [
                'name' => 'Production Site',
            ],
            [
                'name' => 'Distribution Center',
            ],
            [
                'name' => 'Regional Office',
            ],
        ];

        $sitesCount = count($sites);
        $financers = Financer::all();

        foreach ($financers as $financer) {
            for ($i = 0; $i < $sitesCount; $i++) {
                Site::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $sites[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
