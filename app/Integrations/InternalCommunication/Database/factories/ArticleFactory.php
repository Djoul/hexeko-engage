<?php

namespace App\Integrations\InternalCommunication\Database\factories;

use App;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\Financer;
use App\Models\User;
use DateTime;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Article>
     */
    protected $model = Article::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'financer_id' => Financer::factory(),
            'author_id' => User::factory(),
            'deleted_at' => null,
        ];
    }

    /**
     * Indicate that the article is unpublished.
     */
    public function unpublished(): self
    {
        return $this;
    }

    /**
     * Indicate that the article is published.
     */
    /**
     * The status of the article.
     */
    private string $articleStatus = StatusArticleEnum::DRAFT;

    public function published(): self
    {
        $this->articleStatus = StatusArticleEnum::PUBLISHED;
        $this->publishedAt = $this->faker->dateTimeBetween('-1 month', 'now');

        return $this;
    }

    /**
     * The published_at date for translations.
     */
    private ?DateTime $publishedAt = null;

    /**
     * Indicate that the article is pending review.
     */
    public function pending(): self
    {
        $this->articleStatus = StatusArticleEnum::PENDING;
        //        $this->publishedAt = null;

        return $this;
    }

    /**
     * Set specific tags for the article.
     *
     * @param  array<string>  $tags
     */
    public function withTags(array $tags): self
    {
        return $this->state(fn (array $attributes): array => [
            'tags' => $tags,
        ]);
    }

    /**
     * Configure the factory to create translations.
     *
     * @param  array<int, array<string, mixed>>  $translations  [ 'en' => [data], 'fr' => [data], ... ]
     *                                                          If the key exists but value is null/empty, generate fake data for this language.
     *                                                          If the key does not exist, fallback to App::currentLocale() with fake data.
     */
    public function withTranslations(array $translations = []): self
    {
        return $this->afterCreating(function (Article $article) use ($translations): void {
            // If array is empty, create one translation with current locale and fake data
            if ($translations === []) {
                $lang = App::currentLocale();
                $translation = $article->translations()->create([
                    'language' => $lang,
                    'title' => $this->faker->sentence(),
                    'content' => [
                        'type' => 'doc',
                        'content' => [['insert' => $this->faker->paragraph()]],
                    ],
                    'status' => $this->articleStatus,
                    'published_at' => $this->publishedAt,
                ]);

                return;
            }
            foreach ($translations as $lang => $data) {
                // If key is numeric, treat value as language code and generate fake data
                if (is_int($lang)) {
                    $lang = $data;
                    $data = [];
                }
                /** @var array<string, mixed> $data */
                $article->translations()->create(array_merge([
                    'language' => $lang,
                    'title' => $data['title'] ?? $this->faker->sentence(),
                    'content' => $data['content'] ?? [
                        'type' => 'doc',
                        'content' => [['insert' => $this->faker->paragraph()]],
                    ],
                    'status' => $data['status'] ?? $this->articleStatus,
                    'published_at' => $data['published_at'] ?? $this->publishedAt,
                ], $data));
            }
        });
    }
}
