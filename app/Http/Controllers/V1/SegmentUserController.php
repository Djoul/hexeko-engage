<?php

namespace App\Http\Controllers\V1;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Segment\IndexSegmentUserRequest;
use App\Http\Resources\Segment\SegmentUserResource;
use App\Models\Segment;
use Dedoc\Scramble\Attributes\QueryParameter;
use Gate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SegmentUserController extends Controller
{
    /**
     * List users for a segment
     *
     * Return a list of users for a segment with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    public function index(Segment $segment, IndexSegmentUserRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('view', $segment);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        return SegmentUserResource::collection($segment->users()->orderBy('last_name', 'asc')->paginate($perPage));
    }

    /**
     * Get computed users for a segment
     *
     * Return a list of computed users for a segment with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    public function computed(Segment $segment, IndexSegmentUserRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('view', $segment);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        return SegmentUserResource::collection($segment->computedUsers()->orderBy('last_name', 'asc')->paginate($perPage));
    }
}
