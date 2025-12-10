<?php

namespace Database\Factories;

use App\Enums\Integrations\IntegrationTypes;
use App\Models\Integration;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {

        return [
            'id' => Uuid::uuid4()->toString(),
            'module_id' => Module::firstOr(function () {
                return Module::factory()->create();
            })->id,
            'name' => $this->faker->name(),
            'type' => $this->faker->randomElement(IntegrationTypes::asArray()),
            'description' => $this->faker->text(),
            'active' => $this->faker->boolean(),
            'settings' => [],
            'api_endpoint' => $this->faker->slug(2),
            'front_endpoint' => $this->faker->slug(2),
            'resources_count_query' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
