<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Module;
use App\Models\ModulePricingHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPricingHistoryAction
{
    /**Z
     * Record a pricing change in the history table with temporal validity.
     */
    public function execute(
        Module $module,
        string $entityId,
        string $entityType,
        ?int $oldPrice,
        ?int $newPrice,
        string $priceType,
        ?string $reason = null,
        ?Carbon $validFrom = null
    ): void {
        // Only record if price actually changed
        if ($oldPrice === $newPrice) {
            return;
        }

        DB::transaction(function () use ($module, $entityId, $entityType, $oldPrice, $newPrice, $priceType, $reason, $validFrom): void {
            // Default valid_from to first day of next month if not provided
            $validFrom = $validFrom ?? Carbon::now()->startOfMonth()->addMonth();

            // Close previous history entry at end of current month
            if ($oldPrice !== null) {
                ModulePricingHistory::where('entity_id', $entityId)
                    ->where('entity_type', $entityType)
                    ->where('module_id', $module->id)
                    ->where('price_type', $priceType)
                    ->whereNull('valid_until')
                    ->update(['valid_until' => Carbon::now()->endOfMonth()]);
            }

            // Create new history entry with temporal validity
            DB::table('module_pricing_history')->insert([
                'id' => Str::uuid()->toString(),
                'module_id' => $module->id,
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'old_price' => $oldPrice,
                'new_price' => $newPrice,
                'price_type' => $priceType,
                'changed_by' => Auth::id(),
                'reason' => $reason,
                'valid_from' => $validFrom,
                'valid_until' => null, // Open-ended until next change
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Activity Log for audit
            activity('module_pricing')
                ->performedOn($module)
                ->withProperties([
                    'entity_id' => $entityId,
                    'entity_type' => $entityType,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'price_type' => $priceType,
                    'valid_from' => $validFrom->toDateString(),
                ])
                ->log("Module price updated from {$oldPrice} to {$newPrice} cents - effective from {$validFrom->toDateString()}");
        });
    }
}
