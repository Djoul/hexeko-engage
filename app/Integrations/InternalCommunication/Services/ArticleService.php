<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Services;

use App\Integrations\InternalCommunication\Actions\CreateArticleAction;
use App\Integrations\InternalCommunication\Models\Article;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Str;

/**
 * Class ArticleService
 *
 * Handles article operations with automatic filtering through HasFinancer global scope
 * and ArticlePipeline filters (status, segment, etc.)
 */
class ArticleService
{
    public function __construct(
        protected ArticleIllustrationImageService $illustrationImageService
    ) {}

    /**
     * Get all articles.
     *
     * Note: All filtering (financer_id, status, segment, etc.) is handled automatically by:
     * - HasFinancer global scope (financer_id filtering)
     * - ArticlePipeline filters (status, segment_id, etc.)
     * - StatusFilter applies VIEW_DRAFT_ARTICLE permission check
     *
     * @param  array<int, string>  $relations
     */
    public function all(int $perPage = 20, int $page = 1, array $relations = []): Collection
    {
        // Apply pipeline filtering (includes status, segment_id, etc.)
        $query = Article::with($relations)->pipeFiltered();

        $items = $query->get();
        $total = $items->count();

        if (paginated()) {
            $items = $items->forPage($page, $perPage);
        }

        return collect([
            'items' => $items,
            'meta' => [
                'total_items' => $total,
            ],
        ]);
    }

    /**
     * Find an article by ID or create it if it doesn't exist
     *
     * @param  string  $id  The article ID
     * @param  array<int, string>  $relations  The relations to load
     * @param  array<string, mixed>  $validatedData  The data to create the article with if it doesn't exist
     * @return Article The found or created article
     */
    public function findOrCreate(string $id, array $relations = [], array $validatedData = []): Article
    {
        // Bypass global scope (HasFinancerScope) when finding by unique ID
        // This prevents false negatives when article exists but with different financer_id
        $article = Article::withoutGlobalScopes()->with($relations)->find($id);

        if (! $article instanceof Article) {
            $validatedData['id'] = $id;
            $article = resolve(CreateArticleAction::class)->handle($validatedData);
        } else {
            Log::info('[ArticleService::findOrCreate] Article found', [
                'article_id' => $article->id,
            ]);
        }

        return $article;
    }

    /**
     * Find an article by ID.
     *
     * Note: HasFinancer global scope automatically filters by current financer.
     *
     * @param  array<int, string>  $relations
     *
     * @throws ModelNotFoundException
     */
    public function find(string $id, array $relations = [], bool $pipeFiltered = false): Article
    {
        $article = Article::with($relations)->find($id);

        if (! $article instanceof Article) {
            throw new ModelNotFoundException('Article not found');
        }

        return $article;
    }

    /**
     * Update article illustration
     *
     * @param  Article  $article  The article to update
     * @param  string|null  $illustration  The path to the illustration file or null to deactivate all illustrations
     */
    public function updateIllustration(Article $article, ?string $illustration): void
    {
        if ($illustration === null) {
            // If illustration is null, deactivate all illustrations
            $article->media()->whereJsonContains('custom_properties->active', true)
                ->update(['custom_properties' => ['active' => false]]);
        } elseif (! Str::isUrl($illustration, ['http', 'https'])) {
            // If illustration is a string but not an url string it will be a base64
            $this->illustrationImageService->updateIllustration($article, $illustration);
        } else {
            // If request contains media_id
            $mediaId = request()->input('media_id');

            if ($mediaId) {
                // If illustration is a string but an url string
                // First, deactivate all illustrations
                $article->media()->whereJsonContains('custom_properties->active', true)
                    ->update(['custom_properties' => ['active' => false]]);

                // activate the media with that ID
                $article->media()->where('id', $mediaId)
                    ->update(['custom_properties' => ['active' => true]]);
            }
        }
    }
}
