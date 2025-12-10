<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Models\Survey;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    protected $model = Submission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financer_id' => Financer::factory(),
            'user_id' => User::factory(),
            'survey_id' => Survey::factory(),
            'started_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the submission is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'completed_at' => $this->faker->dateTimeBetween($attributes['started_at'] ?? '-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the submission is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'started_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'completed_at' => null,
        ]);
    }
}
