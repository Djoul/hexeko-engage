<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Financer;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

class SetFinancerModulePriceAction
{
    public function __construct(
        private readonly RecordPricingHistoryAction $recordHistory
    ) {}

    /**
     * Set the price for a module at the financer level (overrides division price).
     */
    public function execute(
        Financer $financer,
        Module $module,
        ?int $pricePerBeneficiary,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($financer, $module, $pricePerBeneficiary, $reason): void {
            // Get current price if exists
            $currentPivot = $financer->modules()
                ->where('module_id', $module->id)
                ->first();

            $oldPrice = $currentPivot?->pivot?->price_per_beneficiary;

            // Update or create the pivot record
            if ($currentPivot) {
                $financer->modules()->updateExistingPivot($module->id, [
                    'price_per_beneficiary' => $pricePerBeneficiary,
                ]);
            } else {
                // If no pivot exists, create it with the price
                $financer->modules()->attach($module->id, [
                    'active' => false,
                    'promoted' => false,
                    'price_per_beneficiary' => $pricePerBeneficiary,
                ]);
            }

            // Record pricing history with temporal validity
            $this->recordHistory->execute(
                $module,
                $financer->id,
                'financer',
                $oldPrice,
                $pricePerBeneficiary,
                'module_price',
                $reason,
                null // Will default to first day of next month
            );

            refreshModelCache($module);
            refreshModelCache($financer);
        });
    }
}
