<?php

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Integrations\Survey\Models\Questionnaire;
use Database\Factories\FinancerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Questionnaire>
 */
class QuestionnaireFactory extends Factory
{
    protected $model = Questionnaire::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => [
                'en-GB' => $this->faker->sentence(3),
                'fr-FR' => $this->faker->sentence(3),
            ],
            'description' => [
                'en-GB' => $this->faker->paragraph(),
                'fr-FR' => $this->faker->paragraph(),
            ],
            'instructions' => [
                'en-GB' => $this->faker->paragraph(),
                'fr-FR' => $this->faker->paragraph(),
            ],
            'type' => $this->faker->randomElement(QuestionnaireTypeEnum::getValues()),
            'financer_id' => FinancerFactory::new(),
            'settings' => [
                'allow_multiple_responses' => $this->faker->boolean(),
                'show_progress' => $this->faker->boolean(),
            ],
            'is_default' => $this->faker->boolean(20), // 20% chance of being default
        ];
    }

    /**
     * Indicate that the questionnaire is default.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => true,
        ]);
    }

    /**
     * Indicate that the questionnaire is not default.
     */
    public function notDefault(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_default' => false,
        ]);
    }

    /**
     * Set the questionnaire type.
     */
    public function type(QuestionnaireTypeEnum $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    /**
     * Set the financer ID.
     */
    public function financer(int $financerId): static
    {
        return $this->state(fn (array $attributes): array => [
            'financer_id' => $financerId,
        ]);
    }
}
