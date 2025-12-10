<?php

namespace App\Actions\Financer;

use App\Actions\Module\ActivateModuleForFinancerAction;
use App\Actions\Module\DeactivateModuleForFinancerAction;
use App\Actions\Module\SetFinancerCorePriceAction;
use App\Actions\Module\SetFinancerModulePriceAction;
use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\FinancerLogoService;
use App\Services\Models\FinancerService;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function activity;

class UpdateFinancerAction
{
    public function __construct(
        protected FinancerService $financerService,
        protected SetFinancerCorePriceAction $setCorePriceAction,
        protected ModuleService $moduleService,
        protected ActivateModuleForFinancerAction $activateModuleAction,
        protected DeactivateModuleForFinancerAction $deactivateModuleAction,
        protected SetFinancerModulePriceAction $setPriceAction,
        protected FinancerLogoService $logoService
    ) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(Financer $financer, array $validatedData): Financer
    {
        return DB::transaction(function () use ($financer, $validatedData): Financer {
            // Extract modules if present
            $modules = null;
            if (array_key_exists('modules', $validatedData)) {
                $modules = $validatedData['modules'];
                unset($validatedData['modules']);
            }

            // Extract core_package_price if present
            $corePackagePrice = null;
            if (array_key_exists('core_package_price', $validatedData)) {
                $value = $validatedData['core_package_price'];
                $corePackagePrice = is_int($value) || is_null($value)
                    ? $value
                    : (is_numeric($value) ? (int) $value : null);
                unset($validatedData['core_package_price']);
            }

            // Extract logo if present
            $logo = null;
            $logoRemove = false;
            if (array_key_exists('logo', $validatedData)) {
                $logo = $validatedData['logo'];
                $logoRemove = $logo === null;
                unset($validatedData['logo']);
            }

            // Vérifier si le statut actif est modifié
            $activeChanged = array_key_exists('active', $validatedData) && $validatedData['active'] !== $financer->active;
            $newActiveStatus = $activeChanged ? $validatedData['active'] : null;

            // Mettre à jour le financeur avec toutes les données en une seule requête
            $updatedFinancer = $this->financerService->update($financer, $validatedData);

            // Update core package price if provided
            if ($corePackagePrice !== null && is_int($corePackagePrice)) {
                $this->setCorePriceAction->execute($updatedFinancer, $corePackagePrice);
            }

            // Process modules if provided
            if (is_array($modules)) {
                /** @var array<int, array{id: string, active: bool, promoted?: bool, price_per_beneficiary?: int|null}> $modules */
                $this->processModules($updatedFinancer, $modules);
            }

            // Si le statut actif a été modifié, journaliser cette action spécifique
            if ($activeChanged) {
                $statusText = $newActiveStatus ? 'activé' : 'désactivé';
                Log::info("Financeur {$updatedFinancer->name} {$statusText}");

                if (function_exists('activity')) {
                    activity('financer')
                        ->performedOn($updatedFinancer)
                        ->log("Financeur {$updatedFinancer->name} {$statusText}");
                }
            }

            // Process logo if present
            if ($logo !== null && is_string($logo)) {
                $this->logoService->updateLogo($updatedFinancer, $logo);
            } elseif ($logoRemove) {
                $this->logoService->removeLogo($updatedFinancer);
            }

            // Refresh and load modules relationship if modules were updated
            if (is_array($modules)) {
                $updatedFinancer->refresh()->load('modules');
            }

            return $updatedFinancer;
        });
    }

    /**
     * Process modules array for the financer
     *
     * @param  array<int, array{id: string, active: bool, promoted?: bool, price_per_beneficiary?: int|null}>  $modules
     */
    protected function processModules(Financer $financer, array $modules): void
    {
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
            $moduleId = is_string($moduleData['id']) ? $moduleData['id'] : '';
            if ($moduleId === '') {
                continue;
            }

            $module = $this->moduleService->find($moduleId);

            // Handle core module protection
            if ($module->is_core) {
                // Core modules cannot be deactivated
                if (! $moduleData['active']) {
                    continue; // Skip deactivation attempt
                }
                // Core modules cannot have custom pricing
                if (array_key_exists('price_per_beneficiary', $moduleData) && $moduleData['price_per_beneficiary'] !== null) {
                    $moduleData['price_per_beneficiary'] = null; // Force null price for core modules
                }
            }

            // Process module activation/deactivation
            if ($moduleData['active']) {
                // Check if module is already attached and active
                $existingPivot = $financer->modules()
                    ->where('module_id', $module->id)
                    ->first();

                if (! $existingPivot || ! $existingPivot->pivot->active) {
                    // Activate module if not already active
                    $this->activateModuleAction->execute($module, $financer);
                }

                // Update promoted flag if provided
                if (array_key_exists('promoted', $moduleData)) {
                    $financer->modules()->updateExistingPivot($module->id, [
                        'promoted' => $moduleData['promoted'],
                    ]);
                }

                // Handle price update if provided
                if (array_key_exists('price_per_beneficiary', $moduleData)) {
                    $price = $moduleData['price_per_beneficiary'];
                    $priceValue = is_numeric($price) ? (int) $price : null;

                    $this->setPriceAction->execute(
                        $financer,
                        $module,
                        $priceValue,
                        'Price updated through financer update'
                    );
                }
            } elseif (! $module->is_core) {
                // Deactivate non-core module
                $this->deactivateModuleAction->execute($module, $financer);
            }
        }
    }
}
