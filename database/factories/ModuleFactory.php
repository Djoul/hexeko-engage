<?php

namespace Database\Factories;

use App\Enums\ModulesCategories;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

class ModuleFactory extends Factory
{
    protected $model = Module::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = $this->faker->name();
        $description = $this->faker->text();

        return [
            'id' => Uuid::uuid4()->toString(),
            'name' => [
                'fr-FR' => $name,
                'en-US' => $name,
            ],
            'description' => [
                'fr-FR' => $description,
                'en-US' => $description,
            ],
            'active' => $this->faker->boolean(),
            'category' => ModulesCategories::PURCHASING_POWER,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
