<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Financer;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

class SetFinancerCorePriceAction
{
    public function __construct(
        private readonly RecordPricingHistoryAction $recordHistory
    ) {}

    /**
     * Set the core package price for a financer (overrides division core price).
     */
    public function execute(
        Financer $financer,
        ?int $corePackagePrice,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($financer, $corePackagePrice, $reason): void {
            $oldPrice = $financer->core_package_price;

            // Update financer core package price
            $financer->core_package_price = $corePackagePrice;
            $financer->save();

            // Get first core module for history tracking (or create a virtual module record)
            $coreModule = Module::where('is_core', true)->first();

            if ($coreModule) {
                // Record pricing history with temporal validity
                $this->recordHistory->execute(
                    $coreModule,
                    $financer->id,
                    'financer',
                    $oldPrice,
                    $corePackagePrice,
                    'core_package',
                    $reason,
                    null // Will default to first day of next month
                );
            }

            refreshModelCache($financer);

            activity('financer_core_pricing')
                ->performedOn($financer)
                ->withProperties([
                    'old_price' => $oldPrice,
                    'new_price' => $corePackagePrice,
                ])
                ->log("Financer core package price updated from {$oldPrice} to {$corePackagePrice} cents");
        });
    }
}
