<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\InternalCommunication\Actions\CreateArticleAction;
use App\Integrations\InternalCommunication\Actions\DeleteArticleAction;
use App\Integrations\InternalCommunication\Actions\UpdateArticleAction;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Http\Requests\ArticleFormRequest;
use App\Integrations\InternalCommunication\Http\Resources\ArticleResource;
use App\Integrations\InternalCommunication\Http\Resources\TagResource;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\ArticleService;
use App\Integrations\InternalCommunication\Services\TagService;
use App\Integrations\InternalCommunication\Services\TokenQuotaService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Article Controller
 *
 * Note: financer_id filtering is handled automatically by HasFinancer global scope.
 * Status filtering is handled by StatusFilter in ArticlePipeline based on VIEW_DRAFT_ARTICLE permission.
 */
#[Group('Modules/internal-communication/articles')]
class ArticleController extends Controller
{
    /**
     * ArticleController constructor.
     */
    public function __construct(
        protected ArticleService $articleService,
        protected CreateArticleAction $createArticleAction,
        protected UpdateArticleAction $updateArticleAction,
        protected DeleteArticleAction $deleteArticleAction,
        protected TokenQuotaService $tokenQuotaService,
    ) {
        // Authorization is handled by:
        // 1. #[RequiresPermission] attributes on each method
        // 2. Manual $this->authorize() calls where needed
        // Note: authorizeResource() removed because it conflicts with HasFinancerScope
        // during route model binding before Context is set from query parameters
    }

    /**
     * List articles.
     *
     * This route leverages the pipeline pattern to dynamically filter results based on individual model attributes.
     * Filtering is centralized in ArticlePipeline:
     * - financer_id: Auto-filtered by HasFinancer global scope
     * - status: Filtered by StatusFilter based on VIEW_DRAFT_ARTICLE permission
     * - segment_id: Filtered by SegmentIdFilter
     */
    #[RequiresPermission(PermissionDefaults::READ_ARTICLE)]
    #[QueryParameter('search', description: 'Search global on differents fields (title, content, tags)', type: 'string', example: 'laravel')]
    #[QueryParameter('id', description: 'UUID of the article.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('title', description: 'Title of the article (partial search).', type: 'string', example: 'Laravel')]
    #[QueryParameter('language', description: 'The language of the article.', type: 'string', example: 'fr-FR')]
    #[QueryParameter('content', description: 'Content of the article (partial search).', type: 'string', example: 'framework')]
    #[QueryParameter('status', description: 'Status of the article.', type: 'enum', example: 'published')]
    #[QueryParameter('segment_id', description: 'UUID of the segment.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174002')]
    #[QueryParameter('tags', description: 'Tags of the article.', type: 'array', example: "['php', 'framework']")]
    #[QueryParameter('author_id', description: 'UUID of the author.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174001')]
    #[QueryParameter('published_from', description: 'Filter articles published after this date.', type: 'date', example: '2023-01-01')]
    #[QueryParameter('published_to', description: 'Filter articles published before this date.', type: 'date', example: '2023-12-31')]
    #[QueryParameter('date_from', description: 'Filter articles created after this date.', type: 'date', example: '2023-01-01')]
    #[QueryParameter('date_to', description: 'Filter articles created before this date.', type: 'date', example: '2023-12-31')]
    #[QueryParameter('date_from_fields', description: 'Fields to apply date_from filter to.', type: 'array', example: '["created_at", "updated_at"]')]
    #[QueryParameter('date_to_fields', description: 'Fields to apply date_to filter to.', type: 'array', example: '["created_at", "updated_at"]')]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '20')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    #[QueryParameter('order-by', description: 'Ascending sort field (must be in Article::$sortable).', type: 'string', example: 'title')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field (must be in Article::$sortable).', type: 'string', example: 'published_at')]
    public function index(): AnonymousResourceCollection
    {
        $perPageParam = request()->per_page;
        $pageParam = request()->page;

        $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
        $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

        $resource = $this->articleService->all(
            $perPage,
            $page,
            $this->getBasicRelations()
        );

        /** @var Collection<int, Article> $items */
        $items = $resource['items'];

        // Filter translations based on user permissions
        $user = auth()->user();
        $canViewDrafts = $user && $user->hasPermissionTo(PermissionDefaults::VIEW_DRAFT_ARTICLE);

        $items->each(function (Article $article) use ($canViewDrafts): void {
            /** @var Collection<int, ArticleTranslation> $translations */
            $translations = $article->translations;

            // Filter translations: only show published if user can't view drafts
            $filteredTranslations = $canViewDrafts
                ? $translations
                : $translations->filter(function (ArticleTranslation $translation): bool {
                    $value = is_string($translation->status)
                        ? $translation->status
                        : $translation->status;

                    return $value === StatusArticleEnum::PUBLISHED;
                });

            $article->setRelation('translations', $filteredTranslations);

            // Filter media to only show illustrations
            $article->setRelation('media', $article->media->filter(function (Media $media): bool {
                return $media->collection_name === 'illustration';
            }));
        });

        $financerId = activeFinancerID();
        $financerIdString = is_string($financerId) ? $financerId : null;
        $aiTokenQuota = $this->getAiTokenQuota($financerIdString);

        return ArticleResource::collection($resource['items'])->additional([
            'meta' => array_merge($resource['meta'], [
                'all_tags' => TagResource::collection($this->getVisibleTags()),
                'ai_token_quota' => $aiTokenQuota,
            ]),
        ]);
    }

    /**
     * Show article.
     */
    #[QueryParameter('language', description: 'The language of the article.', type: 'string', example: 'fr-FR')]
    #[RequiresPermission(PermissionDefaults::READ_ARTICLE)]
    public function show(string $id): JsonResponse
    {
        try {
            $article = $this->articleService->find($id, $this->getBasicRelations());
            $articleResource = new ArticleResource($article);

            $financerId = activeFinancerID();
            $financerIdString = is_string($financerId) ? $financerId : null;
            $aiTokenQuota = $this->getAiTokenQuota($financerIdString);

            return response()->json([
                'data' => $articleResource,
                'meta' => [
                    'ai_token_quota' => $aiTokenQuota,
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['error' => 'Article not found'], 404);
        }
    }

    /**
     * Create article.
     *
     * Note: financer_id is automatically set from activeFinancerID()
     * and should not be provided in the request.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_ARTICLE)]
    public function store(ArticleFormRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Auto-assign financer_id from activeFinancerID()
        $validatedData['financer_id'] = activeFinancerID();

        try {
            // Extract and handle illustration separately
            $illustration = $validatedData['illustration'] ?? null;
            unset($validatedData['illustration']);

            // Extract tags to sync after creation
            $tags = $validatedData['tags'] ?? null;
            unset($validatedData['tags']);

            // Create the article
            $article = $this->createArticleAction->handle($validatedData);
            $article->refresh();

            // Handle illustration if provided (base64 string)
            if (is_string($illustration) && ! empty($illustration)) {
                $this->articleService->updateIllustration($article, $illustration);
                $article->refresh();
            }

            // Sync tags if provided
            if (is_array($tags)) {
                $article->tags()->sync($tags);
            }

            $article->load($this->getBasicRelations());

            $articleResource = new ArticleResource($article);
            $financerId = activeFinancerID();
            $financerIdString = is_string($financerId) ? $financerId : null;
            $aiTokenQuota = $this->getAiTokenQuota($financerIdString);

            return response()->json([
                'data' => $articleResource,
                'meta' => [
                    'ai_token_quota' => $aiTokenQuota,
                ],
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            Log::error('[ArticleController::store] Error creating article', [
                'environment' => app()->environment(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to create article'], 500);
        }
    }

    /**
     * Update article.
     *
     * Note: financer_id is automatically set from auth()->user()->current_financer_id
     * and should not be provided in the request.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_ARTICLE)]
    public function update(ArticleFormRequest $request, string $id): JsonResponse
    {
        $validatedData = $request->validated();
        Log::debug('ok ', ['id' => $id, 'data' => $validatedData]);
        // Auto-assign financer_id
        $validatedData['financer_id'] = auth()->user()->current_financer_id;

        try {
            $article = $this->articleService->findOrCreate($id, ['versions'], $validatedData);

            if (! $article->wasRecentlyCreated) {
                $article = $this->updateArticleAction->handle($article, $validatedData);
                $article->refresh();
            }

            $article->load($this->getBasicRelations());

            $articleResource = new ArticleResource($article);
            $financerId = activeFinancerID();
            $financerIdString = is_string($financerId) ? $financerId : null;
            $aiTokenQuota = $this->getAiTokenQuota($financerIdString);

            return response()->json([
                'data' => $articleResource,
                'meta' => [
                    'ai_token_quota' => $aiTokenQuota,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('[ArticleController::update] Error creating version on article update', [
                'article_id' => $article->id ?? $id,
                'environment' => app()->environment(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Failed to update article'], 500);
        }
    }

    /**
     * Delete article.
     */
    #[RequiresPermission(PermissionDefaults::DELETE_ARTICLE)]
    public function destroy(string $id): Response
    {
        // Check if article exists (HasFinancer scope auto-filters by financer_id)
        $article = Article::find($id);

        if (! $article) {
            return response()->json(['error' => 'Article not found'], 404);
        }

        return response()->json(['success' => $this->deleteArticleAction->handle($article)])->setStatusCode(204);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAiTokenQuota(?string $financerId): array
    {
        return in_array($financerId, [null, '', '0'], true)
            ? [
                'division_id' => null,
                'division_name' => null,
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
                'percentage_used' => 0.0,
            ]
            : $this->tokenQuotaService->getQuotaForFinancer($financerId);
    }

    /**
     * @return string[]
     */
    protected function getBasicRelations(): array
    {
        return [
            'author',
            'author.roles.permissions',
            'author.permissions',
            'author.financers', // Fix N+1: Load author's financers to avoid query in activeFinancerID()
            'financer',
            'interactions',
            'tags',
            'media',
            'translations.interactions',
            'translations.llmRequests',
            'translations.versions',
            'versions.media',
            'versions.author',
            'versions.author.roles.permissions',
            'versions.author.permissions',
            'versions.author.financers', // Fix N+1: Load version author's financers
            'llmRequests',
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
