<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TranslationActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationActivityLog>
 */
class TranslationActivityLogFactory extends Factory
{
    protected $model = TranslationActivityLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement(['created', 'updated', 'deleted']),
            'target_type' => $this->faker->randomElement(['key', 'value']),
            'target_id' => $this->faker->numberBetween(1, 1000),
            'locale' => $this->faker->randomElement(['en', 'fr', 'es', 'de', null]),
            'before' => $this->faker->boolean(50) ? ['value' => $this->faker->sentence()] : null,
            'after' => $this->faker->boolean(50) ? ['value' => $this->faker->sentence()] : null,
        ];
    }

    /**
     * Indicate that the log is for a created action.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => 'created',
            'before' => null,
            'after' => ['value' => $this->faker->sentence()],
        ]);
    }

    /**
     * Indicate that the log is for an updated action.
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => 'updated',
            'before' => ['value' => $this->faker->sentence()],
            'after' => ['value' => $this->faker->sentence()],
        ]);
    }

    /**
     * Indicate that the log is for a deleted action.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'action' => 'deleted',
            'before' => ['value' => $this->faker->sentence()],
            'after' => null,
        ]);
    }

    /**
     * Indicate that the log is for a key target.
     */
    public function forKey(): static
    {
        return $this->state(fn (array $attributes): array => [
            'target_type' => 'key',
            'locale' => null,
        ]);
    }

    /**
     * Indicate that the log is for a value target.
     */
    public function forValue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'target_type' => 'value',
            'locale' => $this->faker->randomElement(['en', 'fr', 'es', 'de']),
        ]);
    }
}
