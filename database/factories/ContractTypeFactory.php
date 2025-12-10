<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ContractType;
use App\Models\Financer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractType>
 */
class ContractTypeFactory extends Factory
{
    protected $model = ContractType::class;

    public function definition(): array
    {
        return [
            'financer_id' => Financer::factory(),
            'name' => $this->faker->words(2, true),
        ];
    }
}
