<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Financer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class DepartmentDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $departments = [
            [
                'name' => 'Human Resources',
            ],
            [
                'name' => 'Marketing',
            ],
            [
                'name' => 'Finance',
            ],
        ];
        $departmentsCount = count($departments);
        $financers = Financer::all();
        foreach ($financers as $financer) {
            for ($i = 0; $i < $departmentsCount; $i++) {
                Department::create([
                    'id' => Uuid::uuid7()->toString(),
                    'name' => $departments[$i]['name'],
                    'financer_id' => $financer->id,
                ]);
            }
        }
    }
}
