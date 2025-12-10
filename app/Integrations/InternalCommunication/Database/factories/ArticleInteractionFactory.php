<?php

namespace App\Integrations\InternalCommunication\Database\factories;

use App\Integrations\InternalCommunication\Enums\ReactionTypeEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleInteraction>
 */
class ArticleInteractionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ArticleInteraction>
     */
    protected $model = ArticleInteraction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'article_id' => Article::factory(),
            'reaction' => $this->faker->randomElement(ReactionTypeEnum::getValues()),
            'is_favorite' => $this->faker->boolean(),
        ];
    }

    /**
     * Configure the model factory to create an interaction with a specific reaction.
     *
     * @return Factory<ArticleInteraction>
     */
    public function withReaction(string $reaction): Factory
    {
        return $this->state(function (array $attributes) use ($reaction): array {
            return [
                'reaction' => $reaction,
            ];
        });
    }

    /**
     * Configure the model factory to create a favorite interaction.
     *
     * @return Factory<ArticleInteraction>
     */
    public function favorite(): Factory
    {
        return $this->state(function (array $attributes): array {
            return [
                'is_favorite' => true,
            ];
        });
    }

    /**
     * Configure the model factory to create a non-favorite interaction.
     *
     * @return Factory<ArticleInteraction>
     */
    public function notFavorite(): Factory
    {
        return $this->state(function (array $attributes): array {
            return [
                'is_favorite' => false,
            ];
        });
    }
}
