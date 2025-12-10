<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Database\factories;

use App\Integrations\Survey\Models\Favorite;
use App\Integrations\Survey\Models\Survey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    protected $model = Favorite::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'survey_id' => Survey::factory(),
        ];
    }
}
