<?php

declare(strict_types=1);

namespace App\Actions\Module;

use App\Models\Financer;
use App\Models\Module;
use App\Services\Models\ModuleService;
use Illuminate\Support\Facades\Log;

class PromoteModuleForFinancerAction
{
    public function __construct(
        private ModuleService $moduleService
    ) {}

    /**
     * Execute the action to promote/unpromote a module for a financer
     */
    public function execute(Module $module, Financer $financer, bool $promote): bool
    {
        if ($promote) {
            $this->moduleService->promoteForFinancer($module, $financer);

            // Log activity
            if (! app()->environment('testing')) {
                activity()
                    ->performedOn($module)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'financer_id' => $financer->id,
                        'financer_name' => $financer->name,
                        'action' => 'promote',
                    ])
                    ->log('Module promoted for financer');
            }

            if (! app()->environment('testing')) {
                Log::info('Module promoted for financer', [
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'financer_id' => $financer->id,
                    'financer_name' => $financer->name,
                    'user_id' => auth()->id(),
                ]);
            }
        } else {
            $this->moduleService->unpromoteForFinancer($module, $financer);

            // Log activity
            if (! app()->environment('testing')) {
                activity()
                    ->performedOn($module)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'financer_id' => $financer->id,
                        'financer_name' => $financer->name,
                        'action' => 'unpromote',
                    ])
                    ->log('Module unpromoted for financer');
            }

            if (! app()->environment('testing')) {
                Log::info('Module unpromoted for financer', [
                    'module_id' => $module->id,
                    'module_name' => $module->name,
                    'financer_id' => $financer->id,
                    'financer_name' => $financer->name,
                    'user_id' => auth()->id(),
                ]);
            }
        }

        return $promote;
    }
}
