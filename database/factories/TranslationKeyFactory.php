<?php

namespace Database\Factories;

use App\Models\TranslationKey;
use Illuminate\Database\Eloquent\Factories\Factory;

class TranslationKeyFactory extends Factory
{
    protected $model = TranslationKey::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->words(2, true),
            'group' => $this->faker->optional()->randomElement(['auth', 'validation', 'messages', null]),
        ];
    }
}
