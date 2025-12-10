<?php

declare(strict_types=1);

namespace App\Actions\Financer;

use App\Models\Financer;
use App\Services\Models\FinancerService;
use Illuminate\Support\Facades\Log;

class ToggleFinancerActiveStatusAction
{
    public function __construct(protected FinancerService $financerService) {}

    public function handle(Financer $financer, ?bool $active = null, bool $withUpdate = true): Financer
    {
        $newStatus = $active ?? ! $financer->active;

        if ($withUpdate) {
            $financer = $this->financerService->update($financer, ['active' => $newStatus]);
        }

        $statusText = $newStatus ? 'activé' : 'désactivé';
        Log::info("Financeur {$financer->name} {$statusText}");

        activity('financer')
            ->performedOn($financer)
            ->log("Financeur {$financer->name} {$statusText}");

        return $financer;
    }
}
