<?php

namespace App\Actions\Team;

use App\Models\Team;
use App\Services\Models\TeamService;

class UpdateTeamAction
{
    public function __construct(protected TeamService $teamService) {}

    /**
     * run action
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(Team $team, array $validatedData): Team
    {
        return $this->teamService->update($team, $validatedData);
    }
}
