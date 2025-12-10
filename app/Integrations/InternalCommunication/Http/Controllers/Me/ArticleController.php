<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Controllers\Me;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Http\Requests\Me\IndexArticleRequest;
use App\Integrations\InternalCommunication\Http\Resources\ArticleResource;
use App\Integrations\InternalCommunication\Http\Resources\TagResource;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\ArticleService;
use App\Integrations\InternalCommunication\Services\TagService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Gate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

/**
 * My Articles Controller
 *
 * User-scoped article endpoints for mobile/web beneficiary.
 * Automatically applies:
 * - segmented=true (user's segments only)
 * - Published articles only (via StatusFilter + no VIEW_DRAFT_ARTICLE permission)
 * - All standard ArticlePipeline filters
 */
#[Group('Me/Modules/internal-communication/articles')]
class ArticleController extends Controller
{
    public function __construct(
        protected ArticleService $articleService
    ) {}

    /**
     * List my articles.
     *
     * Leverages ArticleService + ArticlePipeline with forced segmented=true.
     * Supports all standard filters: tags, language, is_favorite, published_from/to, search, etc.
     */
    #[RequiresPermission(PermissionDefaults::READ_ARTICLE)]
    #[QueryParameter('search', description: 'Search in title, content, tags.', type: 'string', example: 'laravel')]
    #[QueryParameter('language', description: 'Filter by language.', type: 'string', example: 'fr-FR')]
    #[QueryParameter('tags', description: 'Filter by tag IDs.', type: 'array', example: "['tag-uuid-1', 'tag-uuid-2']")]
    #[QueryParameter('is_favorite', description: 'Filter by favorite status.', type: 'boolean', example: 'true')]
    #[QueryParameter('published_from', description: 'Filter articles published after this date.', type: 'date', example: '2023-01-01')]
    #[QueryParameter('published_to', description: 'Filter articles published before this date.', type: 'date', example: '2023-12-31')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    #[QueryParameter('order-by', description: 'Ascending sort field (title, published_at, created_at).', type: 'string', example: 'published_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field (title, published_at, created_at).', type: 'string', example: 'published_at')]
    public function index(IndexArticleRequest $request): AnonymousResourceCollection
    {
        $user = Auth::user();

        if (! $user) {
            return ArticleResource::collection(
                Article::query()->whereRaw('1 = 0')->paginate(20)
            );
        }

        // Force segmented filtering and published status for Me context
        // Even GOD users only see published articles on /me/* endpoints
        request()->merge([
            'segmented' => true,
            'status' => StatusArticleEnum::PUBLISHED,
        ]);

        $perPageParam = $request->input('per_page');
        $pageParam = $request->input('page');

        $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
        $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

        // Use ArticleService (handles pipeline filtering automatically)
        $resource = $this->articleService->all(
            $perPage,
            $page,
            $this->getBasicRelations()
        );

        /** @var Collection<int, Article> $items */
        $items = $resource['items'];

        // Filter media to only show illustrations
        $items->each(function (Article $article): void {
            $article->setRelation('media', $article->media->filter(function (Media $media): bool {
                return $media->collection_name === 'illustration';
            }));
        });

        return ArticleResource::collection($items)->additional([
            'meta' => array_merge($resource['meta'], [
                'all_tags' => TagResource::collection($this->getVisibleTags()),
            ]),
        ]);
    }

    /**
     * Show my article.
     *
     * Returns a single published article if accessible to the user (segment check).
     */
    #[RequiresPermission(PermissionDefaults::READ_ARTICLE)]
    #[QueryParameter('language', description: 'Filter by language.', type: 'string', example: 'fr-FR')]
    public function show(Article $article): ArticleResource|Response
    {
        Gate::authorize('view', $article);

        $user = Auth::user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check if article is published
        $hasPublishedTranslation = $article->translations()
            ->where('status', StatusArticleEnum::PUBLISHED)
            ->exists();

        if (! $hasPublishedTranslation) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        // Check segment access: article must be in one of user's segments OR have no segment
        $userSegmentIds = $user->segments->pluck('id')->toArray();

        if ($article->segment_id && ! in_array($article->segment_id, $userSegmentIds, true)) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        // Load relations with published translations only
        $article->load($this->getBasicRelations());

        // Filter media to only show illustrations
        $article->setRelation('media', $article->media->filter(function (Media $media): bool {
            return $media->collection_name === 'illustration';
        }));

        // Mark as read (create interaction if it doesn't exist)
        $article->interactions()->firstOrCreate(
            ['user_id' => $user->id],
            ['is_favorite' => false]
        );

        return new ArticleResource($article);
    }

    /**
     * Get basic relations for eager loading.
     *
     * @return string[]
     */
    protected function getBasicRelations(): array
    {
        return [
            'author',
            'financer',
            'tags',
            'media',
            'translations' => function ($query): void {
                $query->where('status', StatusArticleEnum::PUBLISHED);
            },
            'interactions' => function ($query): void {
                $query->where('user_id', Auth::id());
            },
        ];
    }

    /**
     * Get tags visible to the authenticated user based on their role.
     */
    protected function getVisibleTags(): Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        $financerId = activeFinancerID($user);

        if (! empty($financerId)) {
            $tagService = app(TagService::class);
            $tags = $tagService->findByFinancer($financerId);

            return collect($tags);
        }

        if ($user->hasRole(RoleDefaults::GOD) || $user->hasRole(RoleDefaults::HEXEKO_SUPER_ADMIN)) {
            return Tag::select('id', 'label', 'financer_id', 'created_at', 'updated_at')->get();
        }

        if ($user->hasRole(RoleDefaults::DIVISION_ADMIN)) {
            $financerIds = $user->financers()->pluck('id')->toArray();

            return Tag::whereIn('financer_id', $financerIds)
                ->select('id', 'label', 'financer_id', 'created_at', 'updated_at')
                ->get();
        }

        if ($user->hasRole(RoleDefaults::FINANCER_ADMIN)) {
            $financerId = activeFinancerID();
            if (! empty($financerId)) {
                $tagService = app(TagService::class);

                return $tagService->findByFinancer($financerId);
            }

            return collect();
        }

        return collect();
    }
}
