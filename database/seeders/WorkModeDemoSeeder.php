<?php

namespace Database\Seeders;

use App\Models\Financer;
use App\Models\WorkMode;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class WorkModeDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $workModes = [
            [
                'name' => 'On-site',
            ],
            [
                'name' => 'Remote',
            ],
            [
                'name' => 'Hybrid',
            ],
            [
                'name' => 'Flexible',
            ],
        ];

        $workModesCount = count($workModes);
        $financers = Financer::all();

        foreach ($financers as $financer) {
            for ($i = 0; $i < $workModesCount; $i++) {
                WorkMode::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $workModes[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
