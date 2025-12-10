<?php

namespace Database\Seeders;

use App\Models\Financer;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class TagDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tags = [
            [
                'name' => 'Leadership Team',
            ],
            [
                'name' => 'New Employee',
            ],
            [
                'name' => 'In Training',
            ],
            [
                'name' => 'Mentor',
            ],
            [
                'name' => 'Project Alpha Team',
            ],
            [
                'name' => 'Employee of the Month',
            ],
            [
                'name' => 'Certified',
            ],
            [
                'name' => 'Remote',
            ],
        ];

        $tagsCount = count($tags);
        $financers = Financer::all();

        foreach ($financers as $financer) {
            for ($i = 0; $i < $tagsCount; $i++) {
                Tag::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $tags[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
