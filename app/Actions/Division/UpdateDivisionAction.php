<?php

namespace App\Actions\Division;

use App\Actions\Module\SetDivisionCorePriceAction;
use App\Actions\Module\SetDivisionModulePriceAction;
use App\Models\Division;
use App\Models\Module;
use App\Services\Models\DivisionService;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\DB;

class UpdateDivisionAction
{
    public function __construct(
        protected DivisionService $divisionService,
        protected SetDivisionCorePriceAction $setCorePriceAction,
        protected SetDivisionModulePriceAction $setModulePriceAction,
        protected ModuleService $moduleService
    ) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(Division $division, array $validatedData): Division
    {
        return DB::transaction(function () use ($division, $validatedData): Division {
            // Extract core_package_price if present
            $corePackagePrice = null;
            if (array_key_exists('core_package_price', $validatedData)) {
                $value = $validatedData['core_package_price'];
                $corePackagePrice = is_int($value) || is_null($value)
                    ? $value
                    : (is_numeric($value) ? (int) $value : null);
                unset($validatedData['core_package_price']);
            }

            // Extract modules array if present
            $modules = null;
            if (array_key_exists('modules', $validatedData)) {
                $modules = $validatedData['modules'];
                unset($validatedData['modules']);
            }

            // Update division basic data
            $division = $this->divisionService->update($division, $validatedData);

            // Update core package price if provided
            if ($corePackagePrice !== null && is_int($corePackagePrice)) {
                $this->setCorePriceAction->execute($division, $corePackagePrice);
            }

            // Process modules if provided
            if (is_array($modules)) {
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

                    $isActive = array_key_exists('active', $moduleData) && $moduleData['active'];

                    // Check if module is already attached
                    $existingPivot = $division->modules()
                        ->where('module_id', $module->id)
                        ->first();

                    if ($isActive) {
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
                            $this->setModulePriceAction->execute(
                                $division,
                                $module,
                                $priceInt,
                                'Price updated through division update'
                            );
                        }
                    } elseif (! $module->is_core) {
                        // Deactivate module (only if not core)
                        if ($existingPivot) {
                            $division->modules()->updateExistingPivot($module->id, [
                                'active' => false,
                            ]);
                        }
                    }

                    refreshModelCache($module);
                }
            }

            refreshModelCache($division);

            // Refresh and load modules relationship if modules were updated
            if (is_array($modules)) {
                $division->refresh()->load('modules');
            }

            return $division;
        });
    }
}
