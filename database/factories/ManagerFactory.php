<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financer;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Manager>
 */
class ManagerFactory extends Factory
{
    protected $model = Manager::class;

    public function definition(): array
    {
        return [
            'financer_id' => Financer::factory(),
            'name' => [
                'fr-FR' => $this->faker->words(2, true),
                'en-GB' => $this->faker->words(2, true),
            ],
        ];
    }
}
