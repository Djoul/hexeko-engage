<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financer;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'financer_id' => Financer::factory(),
            'name' => $this->faker->word(),
        ];
    }
}
