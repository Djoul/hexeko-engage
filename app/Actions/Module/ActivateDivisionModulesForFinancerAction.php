<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Division;
use App\Models\Financer;
use Illuminate\Support\Facades\Log;

class ActivateDivisionModulesForFinancerAction
{
    /**
     * Activate all active modules from the division for a financer with calculated pricing (division price * 1.1)
     */
    public function execute(Financer $financer): void
    {
        /** @var Division $division */
        $division = $financer->division;

        // Get all active modules from the division
        $activeModules = $division->modules()
            ->wherePivot('active', true)
            ->get();

        // Activate each module for the financer with pricing
        foreach ($activeModules as $module) {
            $divisionModulePivot = $module->pivot;

            // Calculate price_per_beneficiary (division price * 1.1)
            $pricePerBeneficiary = null;
            if ($divisionModulePivot && $divisionModulePivot->price_per_beneficiary !== null) {
                $pricePerBeneficiary = (int) round($divisionModulePivot->price_per_beneficiary * 1.1);
            }

            // Attach module to financer with calculated price
            $financer->modules()->attach($module->id, [
                'active' => true,
                'promoted' => false,
                'price_per_beneficiary' => $pricePerBeneficiary,
            ]);
        }

        if (! app()->environment('testing')) {
            Log::info('Division modules activated for financer', [
                'financer_id' => $financer->id,
                'financer_name' => $financer->name,
                'division_id' => $division->id,
                'modules_count' => $activeModules->count(),
            ]);
        }
    }
}
