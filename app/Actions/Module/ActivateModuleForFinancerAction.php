<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\Log;

class ActivateModuleForFinancerAction
{
    public function __construct(
        private ModuleService $moduleService
    ) {}

    /**
     * Execute the action to activate a module for a financer
     */
    public function execute(Module $module, Financer $financer): Module
    {
        // Activate the module via the service
        $this->moduleService->activateForFinancer($module, $financer);

        // Log activity
        if (! app()->environment('testing')) {
            activity()
                ->performedOn($module)
                ->causedBy(auth()->user())
                ->withProperties([
                    'financer_id' => $financer->id,
                    'financer_name' => $financer->name,
                ])
                ->log('Module activated for financer');
        }

        if (! app()->environment('testing')) {
            Log::info('Module activated for financer', [
                'module_id' => $module->id,
                'module_name' => $module->name,
                'financer_id' => $financer->id,
                'financer_name' => $financer->name,
                'user_id' => auth()->id(),
            ]);
        }

        return $module;
    }
}
