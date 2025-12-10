<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Financer\CreateFinancerAction;
use App\Actions\Financer\DeleteFinancerAction;
use App\Actions\Financer\ToggleFinancerActiveStatusAction;
use App\Actions\Financer\UpdateFinancerAction;
use App\Attributes\RequiresPermission;
use App\Enums\Countries;
use App\Enums\Currencies;
use App\Enums\FinancerStatus;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Enums\TimeZones;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinancerFormRequest;
use App\Http\Requests\ToggleFinancerActiveRequest;
use App\Http\Resources\Division\DivisionResource;
use App\Http\Resources\Financer\FinancerResource;
use App\Http\Resources\Financer\FinancerResourceCollection;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use App\Services\Models\FinancerService;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FinancerController
 */
class FinancerController extends Controller
{
    /**
     * FinancerService constructor.
     */
    public function __construct(protected FinancerService $financerService) {}

    /**
     * Paginated list of financers.
     *
     * This route uses the pipeline to dynamically filter AND sort financers based on their attributes and sorting parameters.
     * Each financer includes its active non-core modules with pricing and promotion details.
     *
     * @response  FinancerResourceCollection<FinancerResource>
     */
    #[RequiresPermission(PermissionDefaults::READ_ANY_FINANCER)]
    // Global search parameter
    #[QueryParameter('search', description: 'Global search across searchable fields: name, registration_number, vat_number, iban, website. Minimum 2 characters required.', type: 'string', example: 'Banque')]
    // Individual field filters
    #[QueryParameter('id', description: 'UUID of the financer.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('name', description: 'Name of the financer (partial search).', type: 'string', example: 'Banque Populaire')]
    #[QueryParameter('external_id', description: 'External ID of the financer.', type: 'string', example: 'EXT123')]
    #[QueryParameter('created_at', description: 'Creation date (format YYYY-MM-DD).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('division_id', description: 'UUID of the division.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174002')]
    #[QueryParameter('registration_number', description: 'Registration number.', type: 'string', example: 'RCS123456')]
    #[QueryParameter('vat_number', description: 'VAT number.', type: 'string', example: 'FR123456789')]
    #[QueryParameter('iban', description: 'IBAN of the financer.', type: 'string', example: 'FR7612345678901234567890123')]
    #[QueryParameter('representative_id', description: 'UUID of the representative.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174003')]
    #[QueryParameter('remarks', description: 'Remarks about the financer.', type: 'string', example: 'Long-term partner')]
    #[QueryParameter('timezone', description: 'Timezone of the financer.', type: 'string', example: 'Europe/Paris')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field (must be in Financer::$sortable).', type: 'string', example: 'name')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field (must be in Financer::$sortable).', type: 'string', example: 'created_at')]
    public function index(): FinancerResourceCollection
    {
        $user = auth()->user();

        $perPageParam = request()->per_page;
        $pageParam = request()->page;

        $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
        $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

        if (! $user) {
            return new FinancerResourceCollection(collect());
        }

        $hasFullAccess = $user->hasAnyRole([
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
        ]);

        if (! filled(request()->input('division_id')) && ! $hasFullAccess) {
            abort(422, 'division_id is required in query string (uuid or uuid,uuid,uuid)');
        }

        // Fetch data with pagination
        $collections = $this->financerService->all($page, $perPage, ['division.modules', 'modules']);
        $resources = $collections['items'];
        $collection = new FinancerResourceCollection($resources);

        // Build pagination metadata
        $totalValue = $collections['meta']['total_items'] ?? 0;
        $total = is_numeric($totalValue) ? (int) $totalValue : 0;

        $meta = [
            'current_page' => $page,
            'from' => ($page - 1) * $perPage + 1,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
            'to' => min($page * $perPage, $total),
            'total' => $total,
        ];

        return $collection->additional([
            'meta' => $meta,
        ]);
    }

    /**
     * Show financer.
     *
     * Returns financer details including active non-core modules with their pricing and promotion status.
     */
    #[RequiresPermission([PermissionDefaults::READ_ANY_FINANCER, PermissionDefaults::READ_OWN_FINANCER])]
    public function show(string $id): FinancerResource
    {
        $financer = $this->financerService->find($id, ['modules']);
        $divisions = Division::get();
        $this->authorize('view', $financer);

        return new FinancerResource($financer)->additional([
            'meta' => [
                'countries' => Countries::asSelectObject(),
                'currencies' => Currencies::asSelectObject(),
                'languages' => Languages::asSelectObjectFromSettings(),
                'timezones' => TimeZones::allWithLabels(),
                'statuses' => FinancerStatus::asSelectObject(),
                'divisions' => DivisionResource::collection($divisions),
                'divisions_array' => $divisions->map(fn ($division): array => [
                    'value' => $division->id,
                    'label' => $division->name,
                ]),
                'users' => User::get()->map(fn ($user): array => [
                    'value' => $user->id,
                    'label' => $user->full_name,
                ]),
            ],
        ]);
    }

    /**
     * Store financer.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_FINANCER)]
    public function store(FinancerFormRequest $request, CreateFinancerAction $createFinancerAction): JsonResponse
    {
        $validatedData = $request->validated();

        $financer = $createFinancerAction->handle($validatedData);

        return (new FinancerResource($financer))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update financer.
     *
     * Updates financer details and optionally manages module associations.
     * Returns updated financer with active non-core modules if modules were modified.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_FINANCER)]
    public function update(
        FinancerFormRequest $request,
        string $id,
        UpdateFinancerAction $updateFinancerAction
    ): FinancerResource {
        $validatedData = $request->validated();

        $financer = $this->financerService->find($id);

        $financer = $updateFinancerAction->handle($financer, $validatedData);

        return new FinancerResource($financer);
    }

    /**
     * Delete financer.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_FINANCER)]
    public function destroy(string $id, DeleteFinancerAction $deleteFinancerAction): Response
    {
        $financer = $this->financerService->find($id);

        return response()->json(['success' => $deleteFinancerAction->handle($financer)])->setStatusCode(204);
    }

    /**
     * Toggle financer active status.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_FINANCER)]
    public function toggleActive(
        ToggleFinancerActiveRequest $request,
        string $id,
        ToggleFinancerActiveStatusAction $toggleFinancerActiveStatusAction
    ): FinancerResource {
        refreshModelCache(Financer::class);

        $financer = $this->financerService->find($id);
        $active = $request->validated('active');

        $financer = $toggleFinancerActiveStatusAction->handle(
            $financer,
            $active !== null ? (bool) $active : null
        );

        return new FinancerResource($financer);
    }
}
