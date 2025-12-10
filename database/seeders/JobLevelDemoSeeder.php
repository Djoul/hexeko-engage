<?php

namespace Database\Seeders;

use App\Models\Financer;
use App\Models\JobLevel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class JobLevelDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $jobLevels = [
            [
                'name' => 'Junior',
            ],
            [
                'name' => 'Mid-Level',
            ],
            [
                'name' => 'Senior',
            ],
            [
                'name' => 'Expert',
            ],
            [
                'name' => 'Director',
            ],
        ];

        $jobLevelsCount = count($jobLevels);
        $financers = Financer::all();

        foreach ($financers as $financer) {
            for ($i = 0; $i < $jobLevelsCount; $i++) {
                JobLevel::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $jobLevels[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
