<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Models\Answer;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submission = Submission::factory()->create();

        return [
            'user_id' => $submission->user_id,
            'submission_id' => $submission->id,
            'question_id' => Question::factory(),
            'answer' => [
                'value' => $this->faker->sentence(),
                'additional_info' => $this->faker->optional()->paragraph(),
            ],
        ];
    }
}
