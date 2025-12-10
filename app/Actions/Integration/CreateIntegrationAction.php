<?php

namespace App\Actions\Integration;

use App\Models\Integration;
use App\Services\Models\IntegrationService;

class CreateIntegrationAction
{
    public function __construct(protected IntegrationService $integrationService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Integration
    {
        return $this->integrationService->create($validatedData);
    }
}
