<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Integration\CreateIntegrationAction;
use App\Actions\Integration\DeleteIntegrationAction;
use App\Actions\Integration\ToggleIntegrationForDivisionAction;
use App\Actions\Integration\UpdateIntegrationAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationFormRequest;
use App\Http\Resources\Integration\IntegrationCollection;
use App\Http\Resources\Integration\IntegrationResource;
use App\Services\Models\DivisionService;
use App\Services\Models\FinancerService;
use App\Services\Models\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class IntegrationController
 */
class IntegrationController extends Controller
{
    /**
     * IntegrationService constructor.
     */
    public function __construct(protected IntegrationService $integrationService) {}

    /**
     * List integrations.
     *
     * @response IntegrationCollection<IntegrationResource>
     */
    #[RequiresPermission(PermissionDefaults::USE_INTEGRATION)]
    public function index(): IntegrationCollection
    {
        return new IntegrationCollection($this->integrationService->all(['module']));
    }

    /**
     * Show integration.
     */
    #[RequiresPermission(PermissionDefaults::USE_INTEGRATION)]
    public function show(string $id): IntegrationResource
    {
        return new IntegrationResource($this->integrationService->find($id, ['module']));
    }

    /**
     * Store integration.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_INTEGRATION)]
    public function store(
        IntegrationFormRequest $request,
        CreateIntegrationAction $createIntegrationAction
    ): IntegrationResource {
        $validatedData = $request->validated();

        $integration = $createIntegrationAction->handle($validatedData);

        return new IntegrationResource($integration);
    }

    /**
     * Update integration.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function update(
        IntegrationFormRequest $request,
        string $id,
        UpdateIntegrationAction $updateIntegrationAction
    ): IntegrationResource {
        $validatedData = $request->validated();

        $integration = $this->integrationService->find($id);

        $integration = $updateIntegrationAction->handle($integration, $validatedData);

        return new IntegrationResource($integration);
    }

    /**
     * Delete integration.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_INTEGRATION)]
    public function destroy(string $id, DeleteIntegrationAction $deleteIntegrationAction): Response
    {
        $integration = $this->integrationService->find($id);

        return response()->json(['success' => $deleteIntegrationAction->handle($integration)])->setStatusCode(204);
    }

    /**
     * Activate integration for division.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function activateForDivision(Request $request, DivisionService $divisionService): JsonResponse
    {
        $validatedData = $request->validate([
            'integration_id' => 'required|string|exists:integrations,id',
            'division_id' => 'required|string|exists:divisions,id',
        ]);
        $integration = $this->integrationService->find($validatedData['integration_id']);
        $division = $divisionService->find($validatedData['division_id']);

        $this->integrationService->activateForDivision($integration, $division);

        return response()
            ->json(['message' => 'Integration activated for division successfully']);
    }

    /**
     * Deactivate integration for division.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function deactivateForDivision(Request $request, DivisionService $divisionService): JsonResponse
    {
        $validatedData = $request->validate([
            'integration_id' => 'required|string|exists:integrations,id',
            'division_id' => 'required|string|exists:divisions,id',
        ]);
        $integration = $this->integrationService->find($validatedData['integration_id']);
        $division = $divisionService->find($validatedData['division_id']);

        $this->integrationService->deactivateForDivision($integration, $division);

        return response()
            ->json(['message' => 'Integration deactivated for division successfully']);
    }

    /**
     * Toggle integration activation status for division.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function toggleForDivision(
        Request $request,
        DivisionService $divisionService,
        ToggleIntegrationForDivisionAction $toggleIntegrationAction
    ): JsonResponse {
        $validatedData = $request->validate([
            'integration_id' => 'required|string|exists:integrations,id',
            'division_id' => 'required|string|exists:divisions,id',
        ]);
        $integration = $this->integrationService->find($validatedData['integration_id']);
        $division = $divisionService->find($validatedData['division_id']);

        // Check current state to toggle
        $currentlyActive = $division->integrations()->where('integrations.id', $integration->id)->exists();
        $activate = ! $currentlyActive;

        $isActive = $toggleIntegrationAction->execute($integration, $division, $activate);

        return response()
            ->json([
                'message' => $isActive
                    ? 'Integration activated for division successfully'
                    : 'Integration deactivated for division successfully',
                'active' => $isActive,
            ]);
    }

    /**
     * Bulk toggle integrations for division.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function bulkToggleForDivision(Request $request, DivisionService $divisionService): JsonResponse
    {
        $validatedData = $request->validate([
            'integration_ids' => 'required|array',
            'integration_ids.*' => 'required|string|exists:integrations,id',
            'division_id' => 'required|string|exists:divisions,id',
        ]);

        $division = $divisionService->find($validatedData['division_id']);
        $results = $this->integrationService->bulkToggleForDivision($validatedData['integration_ids'], $division);

        return response()
            ->json([
                'message' => 'Integrations toggled for division successfully',
                'results' => $results,
            ]);
    }

    /**
     * Activate integration for financer.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function activateForFinancer(Request $request, FinancerService $financerService): JsonResponse
    {
        $validatedData = $request->validate([
            'integration_id' => 'required|string|exists:integrations,id',
            'financer_id' => 'required|string|exists:financers,id',
        ]);
        $integration = $this->integrationService->find($validatedData['integration_id']);
        $financer = $financerService->find($validatedData['financer_id'], ['division']);

        $this->integrationService->activateForFinancer($integration, $financer);

        return response()
            ->json(['message' => 'Integration activated for financer successfully']);
    }

    /**
     * Deactivate integration for financer.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_INTEGRATION)]
    public function deactivateForFinancer(Request $request, FinancerService $financerService): JsonResponse
    {
        $validatedData = $request->validate([
            'integration_id' => 'required|string|exists:integrations,id',
            'financer_id' => 'required|string|exists:financers,id',
        ]);
        $integration = $this->integrationService->find($validatedData['integration_id']);
        $financer = $financerService->find($validatedData['financer_id']);

        $this->integrationService->deactivateForFinancer($integration, $financer);

        return response()
            ->json(['message' => 'Integration deactivated for financer successfully']);
    }
}
