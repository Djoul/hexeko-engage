<?php

namespace App\Http\Controllers\V1;

use App\Actions\Segment\CreateSegmentAction;
use App\Actions\Segment\UpdateSegmentAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Segment\IndexSegmentRequest;
use App\Http\Requests\Segment\StoreSegmentRequest;
use App\Http\Requests\Segment\UpdateSegmentRequest;
use App\Http\Resources\Segment\SegmentResource;
use App\Models\Segment;
use App\Pipelines\FilterPipelines\SegmentPipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class SegmentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Segment::class, 'segment');
    }

    /**
     * List segments
     *
     * Return a list of segments with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'Segment Name')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexSegmentRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $segments = Segment::query()
            ->withCount('users')
            ->pipe(function ($query) {
                return resolve(SegmentPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return SegmentResource::collection($segments);
    }

    /**
     * Create segment
     */
    public function store(StoreSegmentRequest $request, CreateSegmentAction $createSegmentAction): SegmentResource
    {
        $segment = $createSegmentAction->execute($request->validated());

        return new SegmentResource($segment);
    }

    /**
     * Show segment
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Segment $segment): SegmentResource
    {
        return new SegmentResource($segment->loadCount('users'));
    }

    /**
     * Update segment
     */
    public function update(UpdateSegmentRequest $request, Segment $segment, UpdateSegmentAction $updateSegmentAction): SegmentResource
    {
        $segment = $updateSegmentAction->execute($segment, $request->validated());

        return new SegmentResource($segment);
    }

    /**
     * Delete segment
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function destroy(Segment $segment): Response
    {
        return response()->json(['success' => $segment->delete()])->setStatusCode(204);
    }
}
