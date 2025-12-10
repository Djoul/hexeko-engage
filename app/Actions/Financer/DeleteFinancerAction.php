<?php

namespace App\Actions\Financer;

use App\Models\Financer;
use App\Services\Models\FinancerService;

class DeleteFinancerAction
{
    public function __construct(protected FinancerService $financerService) {}

    /**
     * run action
     */
    public function handle(Financer $financer): bool
    {
        return $this->financerService->delete($financer);
    }
}
