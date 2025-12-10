<?php

namespace App\Http\Controllers\V1;

use App\Actions\ContractType\CreateContractTypeAction;
use App\Actions\ContractType\UpdateContractTypeAction;
use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Requests\ContractType\IndexContractTypeRequest;
use App\Http\Requests\ContractType\UpdateContractTypeRequest;
use App\Http\Resources\ContractType\ContractTypeResource;
use App\Models\ContractType;
use App\Pipelines\FilterPipelines\ContractTypePipeline;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ContractTypeController
 */
class ContractTypeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ContractType::class, 'contract_type');
    }

    /**
     * List contract types
     *
     * Return a list of contract types with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Filter by name.', type: 'string', example: 'CDI')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default created_at.', type: 'string', example: 'created_at')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexContractTypeRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $contractTypes = ContractType::query()
            ->pipe(function ($query) {
                return resolve(ContractTypePipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return ContractTypeResource::collection($contractTypes);
    }

    /**
     * Create contract type
     */
    public function store(UpdateContractTypeRequest $request, CreateContractTypeAction $createContractTypeAction): ContractTypeResource
    {
        $contractType = $createContractTypeAction->execute($request->validated());

        return new ContractTypeResource($contractType);
    }

    /**
     * Show contract type
     */
    public function show(ContractType $contractType): ContractTypeResource
    {
        return new ContractTypeResource($contractType);
    }

    /**
     * Update contract type
     */
    public function update(UpdateContractTypeRequest $request, ContractType $contractType, UpdateContractTypeAction $updateContractTypeAction): ContractTypeResource
    {
        $contractType = $updateContractTypeAction->execute($contractType, $request->validated());

        return new ContractTypeResource($contractType);
    }

    /**
     * Delete contract type
     */
    public function destroy(ContractType $contractType): Response
    {
        return response()->json(['success' => $contractType->delete()])->setStatusCode(204);
    }
}
