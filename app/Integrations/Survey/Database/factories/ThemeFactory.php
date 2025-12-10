<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Models\Theme;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Theme> */
class ThemeFactory extends Factory
{
    protected $model = Theme::class;

    public function definition(): array
    {
        return [
            'name' => [
                'en' => $this->faker->sentence(3),
                'fr' => $this->faker->sentence(3),
            ],
            'description' => [
                'en' => $this->faker->paragraph(),
                'fr' => $this->faker->paragraph(),
            ],
        ];
    }

    public function default(): self
    {
        return $this->state(function (array $attributes): array {
            return ['is_default' => true];
        });
    }
}
