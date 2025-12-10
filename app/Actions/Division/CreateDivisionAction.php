<?php

namespace App\Actions\Division;

use App\Models\Division;
use App\Services\Models\DivisionService;

class CreateDivisionAction
{
    public function __construct(protected DivisionService $divisionService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Division
    {
        return $this->divisionService->create($validatedData);
    }
}
