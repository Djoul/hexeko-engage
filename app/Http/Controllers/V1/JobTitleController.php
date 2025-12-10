<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\JobTitle\CreateJobTitleAction;
use App\Actions\JobTitle\UpdateJobTitleAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobTitle\IndexJobTitleRequest;
use App\Http\Requests\JobTitle\UpdateJobTitleRequest;
use App\Http\Resources\JobTitle\JobTitleResource;
use App\Models\JobTitle;
use App\Pipelines\FilterPipelines\JobTitlePipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class JobTitleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(JobTitle::class, 'job_title');
    }

    /**
     * List work modes
     *
     * Return a list of work modes with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'JobTitle Name')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexJobTitleRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $jobTitles = JobTitle::query()
            ->pipe(function ($query) {
                return resolve(JobTitlePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return JobTitleResource::collection($jobTitles);
    }

    /**
     * Create work mode
     */
    public function store(UpdateJobTitleRequest $request, CreateJobTitleAction $createJobTitleAction): JobTitleResource
    {
        $jobTitle = $createJobTitleAction->execute($request->validated());

        return new JobTitleResource($jobTitle);
    }

    /**
     * Show work mode
     */
    public function show(JobTitle $jobTitle): JobTitleResource
    {
        return new JobTitleResource($jobTitle);
    }

    /**
     * Update jobTitle
     */
    public function update(UpdateJobTitleRequest $request, JobTitle $jobTitle, UpdateJobTitleAction $updateJobTitleAction): JobTitleResource
    {
        $jobTitle = $updateJobTitleAction->execute($jobTitle, $request->validated());

        return new JobTitleResource($jobTitle);
    }

    /**
     * Delete jobTitle
     */
    public function destroy(JobTitle $jobTitle): Response
    {
        return response()->json(['success' => $jobTitle->delete()])->setStatusCode(204);
    }
}
