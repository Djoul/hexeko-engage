<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1;

use App\Actions\Module\ActivateModuleForFinancerAction;
use App\Actions\Module\BulkToggleFinancerModulesAction;
use App\Actions\Module\BulkToggleModulesAction;
use App\Actions\Module\CreateModuleAction;
use App\Actions\Module\DeactivateModuleForFinancerAction;
use App\Actions\Module\DeleteModuleAction;
use App\Actions\Module\PromoteModuleForFinancerAction;
use App\Actions\Module\SetDivisionCorePriceAction;
use App\Actions\Module\SetDivisionModulePriceAction;
use App\Actions\Module\SetFinancerCorePriceAction;
use App\Actions\Module\SetFinancerModulePriceAction;
use App\Actions\Module\ToggleModuleForDivisionAction;
use App\Actions\Module\UpdateModuleAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateDivisionModuleRequest;
use App\Http\Requests\DeactivateFinancerModuleRequest;
use App\Http\Requests\ModuleFormRequest;
use App\Http\Requests\UpdateDivisionModulesRequest;
use App\Http\Requests\UpdateFinancerModulesRequest;
use App\Http\Resources\Module\DivisionModuleCollection;
use App\Http\Resources\Module\FinancerModuleCollection;
use App\Http\Resources\Module\ModuleCollection;
use App\Http\Resources\Module\ModuleResource;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\DivisionService;
use App\Services\Models\FinancerService;
use App\Services\Models\ModuleService;
use Cache;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ModuleController
 */
class ModuleController extends Controller
{
    /**
     * ModuleService constructor.
     */
    public function __construct(protected ModuleService $moduleService) {}

    /**
     * List modules.
     *
     * @response ModuleCollection<ModuleResource>
     */
    #[RequiresPermission(PermissionDefaults::READ_MODULE)]
    public function index(): ModuleCollection
    {
        return new ModuleCollection($this->moduleService->all());
    }

    /**
     * Show module.
     */
    #[RequiresPermission(PermissionDefaults::READ_MODULE)]
    public function show(string $id): ModuleResource
    {
        return new ModuleResource($this->moduleService->find($id));
    }

    /**
     * Store module.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_MODULE)]
    public function store(ModuleFormRequest $request, CreateModuleAction $createModuleAction): ModuleResource
    {
        $validatedData = $request->validated();

        $module = $createModuleAction->handle($validatedData);

        return new ModuleResource($module);
    }

    /**
     * Update module.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES, PermissionDefaults::MANAGE_DIVISION_MODULES])]
    public function update(
        ModuleFormRequest $request,
        string $id,
        UpdateModuleAction $updateModuleAction
    ): ModuleResource {
        $validatedData = $request->validated();

        $module = $this->moduleService->find($id);

        $module = $updateModuleAction->handle($module, $validatedData);

        return new ModuleResource($module);
    }

    /**
     * Delete module.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::DELETE_MODULE)]
    public function destroy(string $id, DeleteModuleAction $deleteModuleAction): Response
    {
        $module = $this->moduleService->find($id);

        return response()
            ->json(['success' => $deleteModuleAction->handle($module)])
            ->setStatusCode(204);
    }

    /**
     * Activate module for division.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_DIVISION_MODULES])]
    public function activateForDivision(Request $request, DivisionService $divisionService): JsonResponse
    {
        $validatedData = $request->validate([
            'module_id' => 'required|string|exists:modules,id',
            'division_id' => 'required|string|exists:divisions,id',
        ]);
        $module = $this->moduleService->find($validatedData['module_id']);
        $division = $divisionService->find($validatedData['division_id']);

        $this->moduleService->activateForDivision($module, $division);
        refreshModelCache($module);

        return response()
            ->json(['message' => 'Module activated for division successfully']);
    }

    /**
     * Deactivate module for division.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_DIVISION_MODULES])]
    public function deactivateForDivision(
        DeactivateDivisionModuleRequest $request,
        DivisionService $divisionService
    ): JsonResponse {
        $validatedData = $request->validated();

        $moduleId = is_string($validatedData['module_id']) ? $validatedData['module_id'] : '';
        $divisionId = is_string($validatedData['division_id']) ? $validatedData['division_id'] : '';
        $module = $this->moduleService->find($moduleId);
        $division = $divisionService->find($divisionId);

        $this->moduleService->deactivateForDivision($module, $division);
        refreshModelCache($module);

        return response()
            ->json(['message' => 'Module deactivated for division successfully']);
    }

    /**
     * Toggle module activation status for division.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_DIVISION_MODULES])]
    public function toggleForDivision(
        Request $request,
        DivisionService $divisionService,
        ToggleModuleForDivisionAction $toggleModuleAction
    ): JsonResponse {
        $validatedData = $request->validate([
            'module_id' => [
                'required',
                'string',
                'exists:modules,id',
                function ($attribute, $value, $fail): void {
                    $module = Module::find($value);
                    if ($module instanceof Module && $module->is_core) {
                        // Check if trying to deactivate
                        $division_id = request()->input('division_id');
                        if ($division_id) {
                            $division = Division::find($division_id);
                            if ($division instanceof Division && $division->modules()->where('modules.id', $value)->where('division_module.active', true)->exists()) {
                                $fail('Core module cannot be deactivated');
                            }
                        }
                    }
                },
            ],
            'division_id' => 'required|string|exists:divisions,id',
        ]);
        $module = $this->moduleService->find($validatedData['module_id']);
        $division = $divisionService->find($validatedData['division_id']);

        // Check current state to toggle
        $currentlyActive = $division->modules()->where('modules.id', $module->id)->exists();
        $activate = ! $currentlyActive;

        $isActive = $toggleModuleAction->execute($module, $division, $activate);

        refreshModelCache($module);

        return response()
            ->json([
                'message' => $isActive
                    ? 'Module activated for division successfully'
                    : 'Module deactivated for division successfully',
                'active' => $isActive,
            ]);
    }

    /**
     * Bulk toggle modules for division.
     *
     * Toggle multiple modules activation status for a division at once.
     * Core modules cannot be toggled and will return a 422 error if included.
     *
     * @operationId bulkToggleForDivision
     *
     * @response 200 scenario="Success" {"message": "Modules toggled for division successfully", "results": {"module-uuid-1": true, "module-uuid-2": false}}
     * @response 422 scenario="Core Module Error" {"message": "Cannot toggle core modules: Module Name. Core modules must always remain active."}
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_DIVISION_MODULES])]
    public function bulkToggleForDivision(
        Request $request,
        DivisionService $divisionService,
        BulkToggleModulesAction $bulkToggleAction
    ): JsonResponse {
        $validatedData = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'required|string|exists:modules,id',
            'division_id' => 'required|string|exists:divisions,id',
        ]);

        $division = $divisionService->find($validatedData['division_id']);
        $results = $bulkToggleAction->execute($validatedData['module_ids'], $division);

        Module::whereIn('id', $validatedData['module_ids'])
            ->each(function (Module $module): void {
                refreshModelCache($module);
            });

        return response()
            ->json([
                'message' => 'Modules toggled for division successfully',
                'results' => $results,
            ]);
    }

    /**
     * Activate module for financer.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES])]
    public function activateForFinancer(
        Request $request,
        FinancerService $financerService,
        ActivateModuleForFinancerAction $activateAction
    ): JsonResponse {
        $validatedData = $request->validate([
            'module_id' => 'required|string|exists:modules,id',
            'financer_id' => 'required|string|exists:financers,id',
        ]);
        $module = $this->moduleService->find($validatedData['module_id']);
        $financer = $financerService->find($validatedData['financer_id'], ['division']);

        $activateAction->execute($module, $financer);
        refreshModelCache($module);

        return response()
            ->json(['message' => 'Module activated for financer successfully']);
    }

    /**
     * Deactivate module for financer.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES])]
    public function deactivateForFinancer(
        DeactivateFinancerModuleRequest $request,
        FinancerService $financerService,
        DeactivateModuleForFinancerAction $deactivateAction
    ): JsonResponse {
        $validatedData = $request->validated();

        $moduleId = is_string($validatedData['module_id']) ? $validatedData['module_id'] : '';
        $financerId = is_string($validatedData['financer_id']) ? $validatedData['financer_id'] : '';
        $module = $this->moduleService->find($moduleId);
        $financer = $financerService->find($financerId);

        $deactivateAction->execute($module, $financer);
        refreshModelCache($module);

        return response()
            ->json(['message' => 'Module deactivated for financer successfully']);
    }

    /**
     * Bulk toggle modules for financer.
     *
     * Toggle multiple modules activation status for a financer at once.
     * Core modules cannot be toggled and will return a 422 error if included.
     * Modules must be active in financer's division first.
     *
     * @operationId bulkToggleForFinancer
     *
     * @response 200 scenario="Success" {"message": "Modules toggled for financer successfully", "results": {"module-uuid-1": true, "module-uuid-2": false}}
     * @response 422 scenario="Core Module Error" {"message": "Cannot toggle core modules: Module Name. Core modules must always remain active."}
     * @response 422 scenario="Division Not Active" {"message": "Module must be active in the financer's division before activating it for a financer"}
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES])]
    public function bulkToggleForFinancer(
        Request $request,
        FinancerService $financerService,
        BulkToggleFinancerModulesAction $bulkToggleAction
    ): JsonResponse {
        $validatedData = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'required|string|exists:modules,id',
            'financer_id' => 'required|string|exists:financers,id',
        ]);

        $financer = $financerService->find($validatedData['financer_id'], ['division']);
        $results = $bulkToggleAction->execute($validatedData['module_ids'], $financer);

        Module::whereIn('id', $validatedData['module_ids'])
            ->each(function (Module $module): void {
                refreshModelCache($module);
            });

        return response()
            ->json([
                'message' => 'Modules toggled for financer successfully',
                'results' => $results,
            ]);
    }

    /**
     * Promote a module for a financer (only if already active).
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES])]
    public function promoteForFinancer(
        Request $request,
        FinancerService $financerService,
        PromoteModuleForFinancerAction $promoteAction
    ): JsonResponse {
        $validatedData = $request->validate([
            'module_id' => 'required|string|exists:modules,id',
            'financer_id' => 'required|string|exists:financers,id',
        ]);
        $moduleId = is_string($validatedData['module_id']) ? $validatedData['module_id'] : '';
        $financerId = is_string($validatedData['financer_id']) ? $validatedData['financer_id'] : '';
        $module = $this->moduleService->find($moduleId);
        $financer = $financerService->find($financerId);

        $financerModule = $module->financers->where('id', activeFinancerID())->first();
        $isCurrentlyPromoted = ($financerModule !== null && $financerModule->pivot !== null && $financerModule->pivot?->promoted);

        $promoteAction->execute($module, $financer, ! $isCurrentlyPromoted);
        refreshModelCache($module);

        return response()->json(['message' => 'Module promu pour le financeur avec succès']);
    }

    /**
     * Unpromote a module for a financer.
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES])]
    public function unpromoteForFinancer(Request $request, FinancerService $financerService): JsonResponse
    {
        $validatedData = $request->validate([
            'module_id' => 'required|string|exists:modules,id',
            'financer_id' => 'required|string|exists:financers,id',
        ]);
        $moduleId2 = is_string($validatedData['module_id']) ? $validatedData['module_id'] : '';
        $financerId2 = is_string($validatedData['financer_id']) ? $validatedData['financer_id'] : '';
        $module = $this->moduleService->find($moduleId2);
        $financer = $financerService->find($financerId2);

        $this->moduleService->unpromoteForFinancer($module, $financer);
        refreshModelCache($module);

        return response()->json(['message' => 'Promotion annulée pour ce module et ce financeur']);
    }

    /**
     * Pin a module for the authenticated user.
     */
    #[RequiresPermission(PermissionDefaults::PIN_MODULE)]
    public function pinForUser(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'module_id' => 'required|string|exists:modules,id',
        ]);

        $module = $this->moduleService->find($validatedData['module_id']);
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $user->pinnedModules()->syncWithoutDetaching([$module->id]);

        refreshModelCache($module);

        return response()->json(['message' => 'Module épinglé avec succès']);
    }

    /**
     * Unpin a module for the authenticated user.
     */
    #[RequiresPermission(PermissionDefaults::PIN_MODULE)]
    public function unpinForUser(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'module_id' => 'required|string|exists:modules,id',
        ]);
        $module = $this->moduleService->find($validatedData['module_id']);
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }

        $user->pinnedModules()->detach($module->id);

        refreshModelCache($module);

        return response()->json(['message' => 'Module désépinglé avec succès']);
    }

    /**
     * List all modules for a specific division.
     */
    #[RequiresPermission(PermissionDefaults::READ_MODULE)]
    public function listDivisionModules(Division $division): DivisionModuleCollection
    {
        $modules = $this->moduleService->all();
        $divisionModules = $division->modules()->withPivot('active', 'price_per_beneficiary')->get();

        // Merge module data with division-specific data
        /** @var Collection<int, Module> $modules */
        $modules = $modules->map(function (Module $module) use ($divisionModules) {
            $divisionModule = $divisionModules->where('id', $module->id)->first();

            return (object) [
                'id' => $module->id,
                'name' => $module->name,
                'description' => $module->description,
                'is_core' => $module->is_core,
                'active' => $divisionModule instanceof Module && $divisionModule->pivot !== null && $divisionModule->pivot->active,
                'price_per_beneficiary' => $divisionModule instanceof Module && $divisionModule->pivot !== null ? $divisionModule->pivot->price_per_beneficiary : null,
            ];
        });

        return new DivisionModuleCollection($modules);
    }

    /**
     * List all modules for a specific financer.
     */
    #[RequiresPermission(PermissionDefaults::READ_MODULE)]
    public function listFinancerModules(Financer $financer): FinancerModuleCollection
    {
        $modules = $this->moduleService->all();
        $financerModules = $financer->modules()->withPivot('active', 'promoted', 'price_per_beneficiary')->get();

        // Merge module data with financer-specific data
        /** @var Collection<int, Module> $modules */
        $modules = $modules->map(function (Module $module) use ($financerModules) {
            $financerModule = $financerModules->where('id', $module->id)->first();

            return (object) [
                'id' => $module->id,
                'name' => $module->name,
                'description' => $module->description,
                'is_core' => $module->is_core,
                'active' => $financerModule instanceof Module && $financerModule->pivot !== null && $financerModule->pivot->active,
                'promoted' => $module->is_core || ($financerModule instanceof Module && $financerModule->pivot !== null && $financerModule->pivot?->promoted),
                'price_per_beneficiary' => $financerModule instanceof Module && $financerModule->pivot !== null ? $financerModule->pivot->price_per_beneficiary : null,
            ];
        });

        return new FinancerModuleCollection($modules);
    }

    /**
     * Update multiple modules for a division.
     *
     * Update modules status, pricing and activation for a specific division.
     * Core modules cannot be deactivated.
     *
     * @operationId updateDivisionModules
     *
     * @response 200 scenario="Success" {"message": "Division modules updated successfully"}
     * @response 422 scenario="Validation Error" {"message": "The given data was invalid.", "errors": {}}
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_DIVISION_MODULES])]
    public function updateDivisionModules(
        UpdateDivisionModulesRequest $request,
        Division $division,
        BulkToggleModulesAction $bulkToggleAction,
        SetDivisionCorePriceAction $setCorePriceAction,
        SetDivisionModulePriceAction $setPriceAction
    ): JsonResponse {
        $validatedData = $request->validated();

        DB::transaction(function () use ($setCorePriceAction, $validatedData, $division, $setPriceAction): void {
            /** @var array<int, array{id: string, active: bool, price_per_beneficiary?: float|null}> $modules */
            $modules = is_array($validatedData['modules']) ? $validatedData['modules'] : [];
            foreach ($modules as $moduleData) {
                if (! is_array($moduleData)) {
                    continue;
                }
                if (! array_key_exists('id', $moduleData)) {
                    continue;
                }
                /** @var string $moduleId */
                $moduleId = $moduleData['id'];
                $module = $this->moduleService->find($moduleId);

                $this->updateDivisionCore($validatedData, $setCorePriceAction, $division);

                $isActive = array_key_exists('active', $moduleData) && $moduleData['active'];
                if ($isActive) {
                    // Check if module is already attached
                    $existingPivot = $division->modules()
                        ->where('module_id', $module->id)
                        ->first();

                    if (! $existingPivot) {
                        // Attach module if not exists
                        $division->modules()->attach($module->id, [
                            'active' => true,
                            'price_per_beneficiary' => $moduleData['price_per_beneficiary'] ?? null,
                        ]);
                    } else {
                        // Update activation status
                        $division->modules()->updateExistingPivot($module->id, [
                            'active' => true,
                        ]);
                    }

                    // Handle price update through action if price is provided
                    if (array_key_exists('price_per_beneficiary', $moduleData)) {
                        $price = $moduleData['price_per_beneficiary'];
                        $priceInt = is_numeric($price) ? (int) $price : null;
                        $setPriceAction->execute(
                            $division,
                            $module,
                            $priceInt,
                            'Price updated through bulk module update'
                        );
                    }
                } elseif (! $module->is_core) {
                    // Deactivate module (only if not core)
                    $division->modules()->updateExistingPivot($module->id, [
                        'active' => false,
                    ]);
                }

                refreshModelCache($module);
            }
        });

        return response()->json([
            'message' => 'Division modules updated successfully',
        ]);
    }

    /**
     * Update multiple modules for a financer.
     *
     * Update modules status, pricing, activation and promotion for a specific financer.
     * Core modules cannot be deactivated. Module must be active in financer's division first.
     *
     * @operationId updateFinancerModules
     *
     * @response 200 scenario="Success" {"message": "Financer modules updated successfully"}
     * @response 422 scenario="Validation Error" {"message": "Module must be active in the financer's division before activating it for a financer"}
     */
    #[RequiresPermission([PermissionDefaults::UPDATE_MODULE, PermissionDefaults::MANAGE_FINANCER_MODULES])]
    public function updateFinancerModules(
        UpdateFinancerModulesRequest $request,
        Financer $financer,
        ActivateModuleForFinancerAction $activateAction,
        DeactivateModuleForFinancerAction $deactivateAction,
        SetFinancerModulePriceAction $setPriceAction,
        SetFinancerCorePriceAction $setCorePriceAction
    ): JsonResponse {
        $validatedData = $request->validated();

        DB::transaction(function () use ($validatedData, $financer, $activateAction, $deactivateAction, $setPriceAction, $setCorePriceAction): void {
            $modules = $validatedData['modules'] ?? [];
            if (! is_array($modules)) {
                return;
            }

            $this->updateFinancerCore($validatedData, $setCorePriceAction, $financer);

            foreach ($modules as $moduleData) {
                if (! is_array($moduleData)) {
                    continue;
                }
                if (! array_key_exists('id', $moduleData)) {
                    continue;
                }
                if (! array_key_exists('active', $moduleData)) {
                    continue;
                }
                $moduleId = is_scalar($moduleData['id']) ? (string) $moduleData['id'] : '';
                $module = $this->moduleService->find($moduleId);

                if ($moduleData['active']) {
                    // Check if module is already attached and active
                    $existingPivot = $financer->modules()
                        ->where('module_id', $module->id)
                        ->first();

                    if (! $existingPivot || ! $existingPivot->pivot->active) {
                        // Only activate if not already active
                        $activateAction->execute($module, $financer);
                    }

                    // Update promoted flag if provided
                    if (array_key_exists('promoted', $moduleData)) {
                        $financer->modules()->updateExistingPivot($module->id, [
                            'promoted' => $moduleData['promoted'],
                        ]);
                    }

                    // Handle price update through action if price is provided
                    if (array_key_exists('price_per_beneficiary', $moduleData)) {
                        $price = $moduleData['price_per_beneficiary'];
                        $priceValue = is_numeric($price) ? (int) $price : null;

                        $setPriceAction->execute(
                            $financer,
                            $module,
                            $priceValue,
                            'Price updated through bulk module update'
                        );
                    }
                } elseif (! $module->is_core) {
                    // Deactivate module (only if not core)
                    $deactivateAction->execute($module, $financer);
                }

                refreshModelCache($module);
            }
        });

        return response()->json([
            'message' => 'Financer modules updated successfully',
        ]);
    }

    /**
     * Update division core package price.
     *
     * Set the price for the core package of modules at the division level.
     * This price will be applied to all core modules for this division.
     *
     * @operationId updateDivisionCorePrice
     *
     * @response 200 scenario="Success" {"message": "Division core package price updated successfully", "core_package_price": 5000}
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_DIVISION)]
    public function updateDivisionCorePrice(
        Division $division,
        Request $request,
        SetDivisionCorePriceAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'core_package_price' => 'required|integer|min:0|max:9999999',
        ]);

        $action->execute($division, $validated['core_package_price']);

        return response()->json([
            'message' => 'Division core package price updated successfully',
            'core_package_price' => $validated['core_package_price'],
        ]);
    }

    /**
     * Update financer core package price.
     *
     * Set the price for the core package of modules at the financer level.
     * This overrides the division-level pricing for this specific financer.
     *
     * @operationId updateFinancerCorePrice
     *
     * @response 200 scenario="Success" {"message": "Financer core package price updated successfully", "core_package_price": 4500}
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_FINANCER)]
    public function updateFinancerCorePrice(
        Financer $financer,
        Request $request,
        SetFinancerCorePriceAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'core_package_price' => 'required|integer|min:0|max:9999999',
        ]);

        $action->execute($financer, $validated['core_package_price']);

        return response()->json([
            'message' => 'Financer core package price updated successfully',
            'core_package_price' => $validated['core_package_price'],
        ]);
    }

    protected function flushCash(Module $module): void
    {
        // Clear tags separately to avoid CROSSSLOT errors in Redis cluster
        try {
            Cache::tags([$module->getCacheTag()])->flush();
        } catch (Exception $e) {
            Log::warning('Failed to flush cache tag: '.$module->getCacheTag(), ['error' => $e->getMessage()]);
        }

        try {
            Cache::tags([$module->getCacheTag($module->id)])->flush();
        } catch (Exception $e) {
            Log::warning('Failed to flush cache tag: '.$module->getCacheTag($module->id), ['error' => $e->getMessage()]);
        }
    }

    private function updateFinancerCore(
        mixed $validatedData,
        SetFinancerCorePriceAction $setCorePriceAction,
        Financer $financer
    ): void {
        $corePackagePrice = null;
        if (array_key_exists('core_package_price', $validatedData)) {
            $value = $validatedData['core_package_price'];
            $corePackagePrice = is_int($value) || is_null($value)
                ? $value
                : (is_numeric($value) ? (int) $value : null);
            unset($validatedData['core_package_price']);
        }

        // Update core package price if provided
        if ($corePackagePrice !== null && is_int($corePackagePrice)) {
            $setCorePriceAction->execute($financer, $corePackagePrice);
        }
    }

    private function updateDivisionCore(
        mixed $validatedData,
        SetDivisionCorePriceAction $setCorePriceAction,
        Division $division
    ): void {
        $corePackagePrice = null;
        if (array_key_exists('core_package_price', $validatedData)) {
            $value = $validatedData['core_package_price'];
            $corePackagePrice = is_int($value) || is_null($value)
                ? $value
                : (is_numeric($value) ? (int) $value : null);
            unset($validatedData['core_package_price']);
        }

        // Update core package price if provided
        if ($corePackagePrice !== null && is_int($corePackagePrice)) {
            $setCorePriceAction->execute($division, $corePackagePrice);
        }
    }
}
