<?php

namespace App\Http\Controllers\V1;

use App\Actions\Tag\CreateTagAction;
use App\Actions\Tag\UpdateTagAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tag\IndexTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Http\Resources\Tag\TagResource;
use App\Models\Tag;
use App\Pipelines\FilterPipelines\TagPipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TagController
 */
class TagController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Tag::class, 'tag');
    }

    /**
     * List tags
     *
     * Return a list of tags with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'Marketing')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexTagRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $tags = Tag::query()
            ->pipe(function ($query) {
                return resolve(TagPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return TagResource::collection($tags);
    }

    /**
     * Create tag
     */
    public function store(UpdateTagRequest $request, CreateTagAction $createTagAction): TagResource
    {
        $tag = $createTagAction->execute($request->validated());

        return new TagResource($tag);
    }

    /**
     * Show tag
     */
    public function show(Tag $tag): TagResource
    {
        return new TagResource($tag);
    }

    /**
     * Update tag
     */
    public function update(UpdateTagRequest $request, Tag $tag, UpdateTagAction $updateTagAction): TagResource
    {
        $tag = $updateTagAction->execute($tag, $request->validated());

        return new TagResource($tag);
    }

    /**
     * Delete tag
     */
    public function destroy(Tag $tag): Response
    {
        return response()->json(['success' => $tag->delete()])->setStatusCode(204);
    }
}
