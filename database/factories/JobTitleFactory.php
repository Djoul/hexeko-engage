<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financer;
use App\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTitle>
 */
class JobTitleFactory extends Factory
{
    protected $model = JobTitle::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Project manager', 'Data analyst', 'Sales manager', 'HR manager']),
            'financer_id' => Financer::factory(),
        ];
    }
}
