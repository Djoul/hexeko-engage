<?php

namespace App\Actions\Team;

use App\Models\Team;
use App\Services\Models\TeamService;

class CreateTeamAction
{
    public function __construct(protected TeamService $teamService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(array $validatedData): Team
    {
        return $this->teamService->create($validatedData);
    }
}
