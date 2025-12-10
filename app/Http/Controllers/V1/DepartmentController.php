<?php

namespace App\Http\Controllers\V1;

use App\Actions\Department\CreateDepartmentAction;
use App\Actions\Department\UpdateDepartmentAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Department\IndexDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\Department\DepartmentResource;
use App\Models\Department;
use App\Pipelines\FilterPipelines\DepartmentPipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DepartmentController
 */
class DepartmentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Department::class, 'department');
    }

    /**
     * List departments
     *
     * Return a list of departments with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'Department Name')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexDepartmentRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $departments = Department::query()
            ->pipe(function ($query) {
                return resolve(DepartmentPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return DepartmentResource::collection($departments);
    }

    /**
     * Create department
     */
    public function store(UpdateDepartmentRequest $request, CreateDepartmentAction $createDepartmentAction): DepartmentResource
    {
        $department = $createDepartmentAction->execute($request->validated());

        return new DepartmentResource($department);
    }

    /**
     * Show department
     */
    public function show(Department $department): DepartmentResource
    {
        return new DepartmentResource($department);
    }

    /**
     * Update department
     */
    public function update(UpdateDepartmentRequest $request, Department $department, UpdateDepartmentAction $updateDepartmentAction): DepartmentResource
    {
        $department = $updateDepartmentAction->execute($department, $request->validated());

        return new DepartmentResource($department);
    }

    /**
     * Delete department
     */
    public function destroy(Department $department): Response
    {
        return response()->json(['success' => $department->delete()])->setStatusCode(204);
    }
}
