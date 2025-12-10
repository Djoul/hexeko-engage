<?php

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionOption> */
class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition(): array
    {
        return [
            'text' => [
                'fr-FR' => $this->faker->sentence(3),
                'en-GB' => $this->faker->sentence(3),
            ],
            'position' => $this->faker->numberBetween(1, 10),
            'question_id' => Question::factory(),
        ];
    }
}
