<?php

namespace Database\Factories;

use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\DivisionStatus;
use App\Enums\Languages;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'remarks' => $this->faker->text(),
            'country' => $this->faker->randomElement(Countries::getValues()),
            'currency' => $this->faker->randomElement(Currencies::getValues()),
            'timezone' => $this->faker->randomElement(['Europe/Paris', 'America/New_York', 'Asia/Tokyo']),
            'language' => $this->faker->randomElement(Languages::getValues()),
            'status' => $this->faker->randomElement(DivisionStatus::getValues()),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
