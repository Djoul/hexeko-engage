<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Division;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\Log;

class ToggleModuleForDivisionAction
{
    public function __construct(
        private ModuleService $moduleService
    ) {}

    /**
     * Execute the action to toggle a module for a division
     */
    public function execute(Module $module, Division $division, bool $activate): bool
    {
        if ($activate) {
            $this->moduleService->activateForDivision($module, $division);

            // Log activity
            if (! app()->environment('testing')) {
                activity()
                    ->performedOn($module)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'division_id' => $division->id,
                        'division_name' => $division->name,
                        'action' => 'activate',
                    ])
                    ->log('Module activated for division');
            }

            if (! app()->environment('testing')) {
                Log::info('Module activated for division', [
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'division_id' => $division->id,
                    'division_name' => $division->name,
                    'user_id' => auth()->id(),
                ]);
            }
        } else {
            $this->moduleService->deactivateForDivision($module, $division);

            // Log activity
            if (! app()->environment('testing')) {
                activity()
                    ->performedOn($module)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'division_id' => $division->id,
                        'division_name' => $division->name,
                        'action' => 'deactivate',
                    ])
                    ->log('Module deactivated for division');
            }

            if (! app()->environment('testing')) {
                Log::info('Module deactivated for division', [
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'division_id' => $division->id,
                    'division_name' => $division->name,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        return $activate;
    }
}
