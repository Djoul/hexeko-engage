<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    protected $model = Survey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->uuid(),
            'financer_id' => Financer::factory(),
            'title' => [
                'en' => $this->faker->sentence(3),
                'fr' => $this->faker->sentence(3),
            ],
            'description' => [
                'en' => $this->faker->paragraph(),
                'fr' => $this->faker->paragraph(),
            ],
            'welcome_message' => [
                'en' => $this->faker->sentence(),
                'fr' => $this->faker->sentence(),
            ],
            'thank_you_message' => [
                'en' => $this->faker->sentence(),
                'fr' => $this->faker->sentence(),
            ],
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'starts_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'ends_at' => $this->faker->dateTimeBetween('+1 month', '+3 months'),
            'settings' => [
                'theme' => $this->faker->randomElement(['light', 'dark']),
                'notifications' => $this->faker->boolean(),
                'email_alerts' => $this->faker->boolean(),
            ],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'draft',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'published',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDays(1),
        ]);
    }

    /**
     * Create a survey with existing financer
     */
    public function withFinancer(Financer $financer): static
    {
        return $this->state(fn (array $attributes): array => [
            'financer_id' => $financer->id,
        ]);
    }
}
