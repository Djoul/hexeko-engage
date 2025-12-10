<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financer;
use App\Models\WorkMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkMode>
 */
class WorkModeFactory extends Factory
{
    protected $model = WorkMode::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Remote', 'Hybrid', 'Office', 'Flexible']),
            'financer_id' => Financer::factory(),
        ];
    }
}
