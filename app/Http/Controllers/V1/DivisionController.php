<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Division\CreateDivisionAction;
use App\Actions\Division\DeleteDivisionAction;
use App\Actions\Division\UpdateDivisionAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\DivisionFormRequest;
use App\Http\Resources\Division\DivisionResource;
use App\Http\Resources\Division\DivisionResourceCollection;
use App\Services\Models\DivisionService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class DivisionController
 */
class DivisionController extends Controller
{
    /**
     * DivisionService constructor.
     */
    public function __construct(protected DivisionService $divisionService) {}

    /**
     * List divisions.
     *
     * This route leverages the pipeline pattern to dynamically filter results based on individual model attributes.
     * Each division includes its active non-core modules with pricing details.
     *
     * @response DivisionResourceCollection<DivisionResource>
     */
    #[RequiresPermission(PermissionDefaults::READ_DIVISION)]
    // Global search parameter
    #[QueryParameter('search', description: 'Global search across searchable fields: name, remarks, country. Minimum 2 characters required.', type: 'string', example: 'France')]
    // Individual field filters
    #[QueryParameter('id', description: 'UUID of the division.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Name of the division.', type: 'string', example: 'Division Name')]
    #[QueryParameter('remarks', description: 'Remarks about the division.', type: 'string', example: 'Some remarks')]
    #[QueryParameter('country', description: 'Country of the division.', type: 'string', example: 'France')]
    #[QueryParameter('currency', description: 'Currency of the division.', type: 'string', default: 'EUR', example: 'EUR')]
    #[QueryParameter('timezone', description: 'Timezone of the division.', type: 'string', default: 'Europe/Paris', example: 'Europe/Paris')]
    #[QueryParameter('language', description: 'Language of the division.', type: 'string', default: 'fr-FR', example: 'fr-FR')]
    #[QueryParameter('created_at', description: 'Creation timestamp.', type: 'timestamp', example: '2024-01-01T00:00:00.000000Z')]
    #[QueryParameter('updated_at', description: 'Update timestamp.', type: 'timestamp', example: '2024-01-01T12:30:45.000000Z')]
    #[QueryParameter('deleted_at', description: 'Deletion timestamp.', type: 'timestamp', example: '2024-01-01T12:30:45.000000Z')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field (must be in Division::$sortable).', type: 'string', example: 'name')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field (must be in Division::$sortable).', type: 'string', example: 'created_at')]
    public function index(): DivisionResourceCollection
    {
        return new DivisionResourceCollection($this->divisionService->all(['modules']));
    }

    /**
     * Show division.
     *
     * Returns division details including active non-core modules with their pricing.
     */
    #[RequiresPermission(PermissionDefaults::READ_DIVISION)]
    public function show(string $id): DivisionResource
    {
        // Validate $id is a valid UUID and exists in divisions
        $validator = Validator::make(['id' => $id], [
            'id' => ['required', 'uuid', 'exists:divisions,id'],
        ]);
        if ($validator->fails()) {
            abort(404, 'Division not found');
        }

        return new DivisionResource($this->divisionService->find($id, ['modules']));
    }

    /**
     * Store division.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_DIVISION)]
    public function store(DivisionFormRequest $request, CreateDivisionAction $createDivisionAction): DivisionResource
    {
        $validatedData = $request->validated();

        $division = $createDivisionAction->handle($validatedData);

        return new DivisionResource($division);
    }

    /**
     * Update division.
     *
     * Updates division details and optionally manages module associations.
     * Returns updated division with active non-core modules if modules were modified.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_DIVISION)]
    public function update(DivisionFormRequest $request, string $id, UpdateDivisionAction $updateDivisionAction): DivisionResource
    {
        // Validate $id is a valid UUID and exists in divisions
        $validator = Validator::make(['id' => $id], [
            'id' => ['required', 'uuid', 'exists:divisions,id'],
        ]);
        if ($validator->fails()) {
            abort(404, 'Division not found');
        }

        $validatedData = $request->validated();

        $division = $this->divisionService->find($id);

        $division = $updateDivisionAction->handle($division, $validatedData);

        return new DivisionResource($division);
    }

    /**
     * Delete division.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_DIVISION)]
    public function destroy(string $id, DeleteDivisionAction $deleteDivisionAction): Response
    {
        // Validate $id is a valid UUID and exists in divisions
        $validator = Validator::make(['id' => $id], [
            'id' => ['required', 'uuid', 'exists:divisions,id'],
        ]);
        if ($validator->fails()) {
            abort(404, 'Division not found');
        }

        $division = $this->divisionService->find($id);

        return response()->json(['success' => $deleteDivisionAction->handle($division)])->setStatusCode(204);
    }
}
