<?php

namespace App\Actions\Integration;

use App\Models\Integration;
use App\Services\Models\IntegrationService;

class DeleteIntegrationAction
{
    public function __construct(protected IntegrationService $integrationService) {}

    /**
     * run action
     */
    public function handle(Integration $integration): bool
    {
        return $this->integrationService->delete($integration);
    }
}
