<?php

namespace Database\Seeders;

use App\Models\Financer;
use App\Models\JobTitle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class JobTitleDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $jobTitles = [
            [
                'name' => 'Full Stack Developer',
            ],
            [
                'name' => 'Project Manager',
            ],
            [
                'name' => 'Marketing Manager',
            ],
            [
                'name' => 'Financial Analyst',
            ],
            [
                'name' => 'HR Specialist',
            ],
            [
                'name' => 'UX/UI Designer',
            ],
        ];

        $jobTitlesCount = count($jobTitles);
        $financers = Financer::all();

        foreach ($financers as $financer) {
            for ($i = 0; $i < $jobTitlesCount; $i++) {
                JobTitle::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $jobTitles[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
