<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Theme;
use App\Models\Financer;
use Database\Factories\FinancerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Question> */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        /** @var Financer $financer */
        $financer = resolve(FinancerFactory::class)->create();
        /** @var Theme $theme */
        $theme = resolve(ThemeFactory::class)->create(['financer_id' => $financer->id]);

        return [
            'text' => [
                'en-GB' => $this->faker->sentence(3),
                'fr-FR' => $this->faker->sentence(3),
            ],
            'help_text' => [
                'en-GB' => $this->faker->paragraph(),
                'fr-FR' => $this->faker->paragraph(),
            ],
            'type' => $this->faker->randomElement(QuestionTypeEnum::getValues()),
            'theme_id' => $theme->id,
            'financer_id' => $financer->id,
            'metadata' => ['category' => 'general'],
            'is_default' => true,
        ];
    }
}
