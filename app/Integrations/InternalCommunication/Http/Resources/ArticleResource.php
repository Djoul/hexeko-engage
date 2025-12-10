<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\LLMRequest\LLMRequestResourceCollection;
use App\Http\Resources\User\UserResource;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 */
class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        $article = $this;

        $userInteraction = $this->getUserInteraction();

        $currentTranslation = $article->translation();

        return [
            'id' => $article->id,
            'author' => new UserResource($this->whenLoaded('author')),
            'author_id' => $article->author_id,
            'content' => $currentTranslation?->content,
            'created_at' => $article->created_at?->toIso8601String(),
            'financer' => new FinancerResource($this->whenLoaded('financer')),
            'financer_id' => $article->financer_id,
            'is_favorite' => $userInteraction instanceof ArticleInteraction && $userInteraction->is_favorite,
            'illustration' => $this->whenLoaded('media', function () use ($article) {
                $activeMedia = $article->getActiveIllustration();

                if (! $activeMedia) {
                    return '';
                }

                // check if conversion exists before getting the url
                if ($activeMedia->hasGeneratedConversion('illustration')) {
                    // Generate temporary URL for S3 media
                    if (in_array($activeMedia->disk, ['s3', 's3-local'])) {
                        return $activeMedia->getTemporaryUrl(now()->addHour(), 'illustration');
                    }

                    return $activeMedia->getUrl('illustration');
                }
                // Generate temporary URL for S3 media
                if (in_array($activeMedia->disk, ['s3', 's3-local'])) {
                    return $activeMedia->getTemporaryUrl(now()->addHour());
                }

                return $activeMedia->getUrl();
            }, ''),
            'llm_requests' => $this->when(
                $this->relationLoaded('llmRequests'),
                function () {
                    $response = LLMRequestResourceCollection::make($this->llmRequests)->response()->getData(true);

                    return is_array($response) && array_key_exists('data', $response) ? $response['data'] : [];
                }
            ) ?? [],
            'llm_requests_count' => $this->whenLoaded('llmRequests', fn () => $article->llmRequests->count(), 0),
            'published_at' => $currentTranslation?->published_at?->toIso8601String(),
            'reaction' => $userInteraction?->reaction,
            'reactions' => $this->getOptimizedReactions(),
            'reactions_count' => $this->whenLoaded('interactions', fn () => $article->interactions->whereNotNull('reaction')->count(), 0),
            'status' => $currentTranslation?->status,
            'tags' => $this->whenLoaded('tags', fn () => $article->tags),
            'title' => $currentTranslation?->title,
            'translations' => $this->getTranslationsFormated(),
            'updated_at' => $article->updated_at?->toIso8601String(),
            'versions' => $this->when(
                $this->relationLoaded('versions'),
                fn () => ArticleVersionResourceCollection::make($this->versions)
            ),
            'versions_count' => $this->whenLoaded('versions', fn () => $article->versions->count(), 0),
        ];
    }

    /**
     * Get the current user's interaction with this article.
     * Uses eager loaded data to avoid additional queries.
     */
    protected function getUserInteraction(): ?ArticleInteraction
    {

        $userId = auth()->id();
        if (! $userId) {
            return null;
        }

        return $this->interactions->where('user_id', $userId)->first();
    }

    /**
     * Get optimized reactions data without additional queries.
     *
     * @return array<string>
     */
    protected function getOptimizedReactions(): array
    {
        if (! $this->relationLoaded('interactions')) {
            return [];
        }

        /** @var array<string> */
        return $this->interactions
            ->pluck('reaction')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getTranslationsFormated(): array
    {
        $translations = $this->whenLoaded('translations');

        if (! $translations || ! is_object($translations) || ! method_exists($translations, 'keyBy')) {
            return [];
        }

        return $translations->keyBy('language')
            ->map(function ($translation): ArticleTranslationResource {
                return new ArticleTranslationResource($translation);
            })
            ->toArray();
    }
}
