<?php

namespace App\Actions\Module;

use App\Models\Module;
use App\Services\Models\ModuleService;

class CreateModuleAction
{
    public function __construct(protected ModuleService $moduleService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Module
    {
        return $this->moduleService->create($validatedData);
    }
}
