<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Division;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

class SetDivisionModulePriceAction
{
    public function __construct(
        private readonly RecordPricingHistoryAction $recordHistory
    ) {}

    /**
     * Set the price for a module at the division level.
     */
    public function execute(
        Division $division,
        Module $module,
        ?int $pricePerBeneficiary,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($division, $module, $pricePerBeneficiary, $reason): void {
            // Get current price if exists
            $currentPivot = $division->modules()
                ->where('module_id', $module->id)
                ->first();

            /** @var int|null $oldPrice */
            $oldPrice = $currentPivot?->pivot?->getAttribute('price_per_beneficiary');

            // Update or create the pivot record
            if ($currentPivot) {
                $division->modules()->updateExistingPivot($module->id, [
                    'price_per_beneficiary' => $pricePerBeneficiary,
                ]);
            } else {
                // If no pivot exists, create it with the price
                $division->modules()->attach($module->id, [
                    'active' => false,
                    'price_per_beneficiary' => $pricePerBeneficiary,
                ]);
            }

            // Record pricing history with temporal validity
            $this->recordHistory->execute(
                $module,
                $division->id,
                'division',
                $oldPrice,
                $pricePerBeneficiary,
                'module_price',
                $reason,
                null // Will default to first day of next month
            );

            refreshModelCache($module);
            refreshModelCache($division);
        });
    }
}
