<?php

declare(strict_types=1);

namespace App\Actions\Integration;

use App\Models\Division;
use App\Models\Integration;
use App\Services\Models\IntegrationService;
use Illuminate\Support\Facades\Log;

class ToggleIntegrationForDivisionAction
{
    public function __construct(
        private IntegrationService $integrationService
    ) {}

    /**
     * Execute the action to toggle an integration for a division
     */
    public function execute(Integration $integration, Division $division, bool $activate): bool
    {
        if ($activate) {
            $this->integrationService->activateForDivision($integration, $division);

            // Log activity
            if (! app()->environment('testing')) {
                activity()
                    ->performedOn($integration)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'division_id' => $division->id,
                        'division_name' => $division->name,
                        'action' => 'activate',
                    ])
                    ->log('Integration activated for division');
            }

            if (! app()->environment('testing')) {
                Log::info('Integration activated for division', [
                    'integration_id' => $integration->id,
                    'integration_name' => $integration->name,
                    'division_id' => $division->id,
                    'division_name' => $division->name,
                    'user_id' => auth()->id(),
                ]);
            }
        } else {
            $this->integrationService->deactivateForDivision($integration, $division);

            // Log activity
            if (! app()->environment('testing')) {
                activity()
                    ->performedOn($integration)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'division_id' => $division->id,
                        'division_name' => $division->name,
                        'action' => 'deactivate',
                    ])
                    ->log('Integration deactivated for division');
            }

            if (! app()->environment('testing')) {
                Log::info('Integration deactivated for division', [
                    'integration_id' => $integration->id,
                    'integration_name' => $integration->name,
                    'division_id' => $division->id,
                    'division_name' => $division->name,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        return $activate;
    }
}
