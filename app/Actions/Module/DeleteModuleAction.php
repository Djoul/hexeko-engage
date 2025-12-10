<?php

namespace App\Actions\Module;

use App\Models\Module;
use App\Services\Models\ModuleService;

class DeleteModuleAction
{
    public function __construct(protected ModuleService $moduleService) {}

    /**
     * run action
     */
    public function handle(Module $module): bool
    {
        return $this->moduleService->delete($module);
    }
}
