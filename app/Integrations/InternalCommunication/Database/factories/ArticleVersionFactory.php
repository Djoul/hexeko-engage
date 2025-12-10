<?php

namespace App\Integrations\InternalCommunication\Database\factories;

use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArticleVersion>
 */
class ArticleVersionFactory extends Factory
{
    protected $model = ArticleVersion::class;

    public function definition(): array
    {
        return [
            'article_id' => null, // Will be set in the for() method
            'article_translation_id' => null, // Will be set in the for() method
            'version_number' => $this->faker->numberBetween(1, 10),
            'content' => json_encode([
                'body' => $this->faker->paragraphs(3, true),
                'summary' => $this->faker->sentence(),
            ]),
            'prompt' => $this->faker->optional()->sentence(),
            'llm_response' => json_encode($this->faker->optional()->randomElement([
                ['result' => $this->faker->sentence()],
                $this->faker->sentence(),
                null,
            ])),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the factory to use a specific article and translation.
     */
    public function forArticle(Article $article, ?int $translationId = null): self
    {
        $translationId = $translationId ?? $article->translations->first()->id ?? null;

        return $this->state(function (array $attributes) use ($article, $translationId): array {
            return [
                'article_id' => $article->id,
                'article_translation_id' => $translationId,
            ];
        });
    }
}
