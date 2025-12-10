<?php

namespace Database\Factories;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationValueFactory extends Factory
{
    protected $model = TranslationValue::class;

    public function definition(): array
    {
        return [
            'translation_key_id' => TranslationKey::factory(),
            'locale' => $this->faker->randomElement([
                'fr-FR', 'en-GB', 'de-DE', 'es-ES', 'it-IT',
            ]),
            'value' => $this->faker->sentence(3),
        ];
    }
}
