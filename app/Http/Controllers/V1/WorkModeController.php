<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\WorkMode\CreateWorkModeAction;
use App\Actions\WorkMode\UpdateWorkModeAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkMode\IndexWorkModeRequest;
use App\Http\Requests\WorkMode\UpdateWorkModeRequest;
use App\Http\Resources\WorkMode\WorkModeResource;
use App\Models\WorkMode;
use App\Pipelines\FilterPipelines\WorkModePipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class WorkModeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(WorkMode::class, 'work_mode');
    }

    /**
     * List work modes
     *
     * Return a list of work modes with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'WorkMode Name')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexWorkModeRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $workModes = WorkMode::query()
            ->pipe(function ($query) {
                return resolve(WorkModePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return WorkModeResource::collection($workModes);
    }

    /**
     * Create work mode
     */
    public function store(UpdateWorkModeRequest $request, CreateWorkModeAction $createWorkModeAction): WorkModeResource
    {
        $workMode = $createWorkModeAction->execute($request->validated());

        return new WorkModeResource($workMode);
    }

    /**
     * Show work mode
     */
    public function show(WorkMode $workMode): WorkModeResource
    {
        return new WorkModeResource($workMode);
    }

    /**
     * Update workMode
     */
    public function update(UpdateWorkModeRequest $request, WorkMode $workMode, UpdateWorkModeAction $updateWorkModeAction): WorkModeResource
    {
        $workMode = $updateWorkModeAction->execute($workMode, $request->validated());

        return new WorkModeResource($workMode);
    }

    /**
     * Delete workMode
     */
    public function destroy(WorkMode $workMode): Response
    {
        return response()->json(['success' => $workMode->delete()])->setStatusCode(204);
    }
}
