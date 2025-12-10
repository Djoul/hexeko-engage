<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\JobLevel\CreateJobLevelAction;
use App\Actions\JobLevel\UpdateJobLevelAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobLevel\IndexJobLevelRequest;
use App\Http\Requests\JobLevel\UpdateJobLevelRequest;
use App\Http\Resources\JobLevel\JobLevelResource;
use App\Models\JobLevel;
use App\Pipelines\FilterPipelines\JobLevelPipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class JobLevelController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(JobLevel::class, 'job_level');
    }

    /**
     * List job levels
     *
     * Return a list of job levels with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'JobLevel Name')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexJobLevelRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $jobLevels = JobLevel::query()
            ->pipe(function ($query) {
                return resolve(JobLevelPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return JobLevelResource::collection($jobLevels);
    }

    /**
     * Create job level
     */
    public function store(UpdateJobLevelRequest $request, CreateJobLevelAction $createJobLevelAction): JobLevelResource
    {
        $jobLevel = $createJobLevelAction->execute($request->validated());

        return new JobLevelResource($jobLevel);
    }

    /**
     * Show job level
     */
    public function show(JobLevel $jobLevel): JobLevelResource
    {
        return new JobLevelResource($jobLevel);
    }

    /**
     * Update jobLevel
     */
    public function update(UpdateJobLevelRequest $request, JobLevel $jobLevel, UpdateJobLevelAction $updateJobLevelAction): JobLevelResource
    {
        $jobLevel = $updateJobLevelAction->execute($jobLevel, $request->validated());

        return new JobLevelResource($jobLevel);
    }

    /**
     * Delete jobLevel
     */
    public function destroy(JobLevel $jobLevel): Response
    {
        return response()->json(['success' => $jobLevel->delete()])->setStatusCode(204);
    }
}
