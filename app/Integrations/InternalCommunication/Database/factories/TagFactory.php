<?php

namespace App\Integrations\InternalCommunication\Database\factories;

use App\Enums\Languages;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'label' => [
                Languages::DUTCH_BELGIUM => $this->faker->word,
                Languages::FRENCH => $this->faker->word,
                Languages::ENGLISH => $this->faker->word,
            ],
            'financer_id' => Financer::factory(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
