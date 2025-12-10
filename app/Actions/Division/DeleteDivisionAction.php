<?php

namespace App\Actions\Division;

use App\Models\Division;
use App\Services\Models\DivisionService;

class DeleteDivisionAction
{
    public function __construct(protected DivisionService $divisionService) {}

    /**
     * run action
     */
    public function handle(Division $division): bool
    {
        return $this->divisionService->delete($division);
    }
}
