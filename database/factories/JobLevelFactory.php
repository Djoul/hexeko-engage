<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financer;
use App\Models\JobLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobLevel>
 */
class JobLevelFactory extends Factory
{
    protected $model = JobLevel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Junior', 'Intermediate', 'Senior', 'Lead', 'Principal', 'Director']),
            'financer_id' => Financer::factory(),
        ];
    }
}
