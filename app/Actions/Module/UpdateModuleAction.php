<?php

namespace App\Actions\Module;

use App\Models\Module;
use App\Services\Models\ModuleService;

class UpdateModuleAction
{
    public function __construct(protected ModuleService $moduleService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(Module $module, array $validatedData): Module
    {
        return $this->moduleService->update($module, $validatedData);
    }
}
