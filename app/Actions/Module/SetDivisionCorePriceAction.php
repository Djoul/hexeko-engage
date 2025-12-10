<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Division;
use App\Models\Module;
use Illuminate\Support\Facades\DB;

class SetDivisionCorePriceAction
{
    public function __construct(
        private readonly RecordPricingHistoryAction $recordHistory
    ) {}

    /**
     * Set the core package price for a division.
     */
    public function execute(
        Division $division,
        ?int $corePackagePrice,
        ?string $reason = null
    ): void {
        DB::transaction(function () use ($division, $corePackagePrice, $reason): void {
            $oldPrice = $division->core_package_price;

            // Update division core package price
            $division->core_package_price = $corePackagePrice;
            $division->save();

            // Get first core module for history tracking (or create a virtual module record)
            $coreModule = Module::where('is_core', true)->first();

            if ($coreModule) {
                // Record pricing history with temporal validity
                $this->recordHistory->execute(
                    $coreModule,
                    $division->id,
                    'division',
                    $oldPrice,
                    $corePackagePrice,
                    'core_package',
                    $reason,
                    null // Will default to first day of next month
                );
            }

            refreshModelCache($division);

            activity('division_core_pricing')
                ->performedOn($division)
                ->withProperties([
                    'old_price' => $oldPrice,
                    'new_price' => $corePackagePrice,
                ])
                ->log("Division core package price updated from {$oldPrice} to {$corePackagePrice} cents");
        });
    }
}
