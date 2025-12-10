<?php

namespace App\Actions\Team;

use App\Models\Team;
use App\Services\Models\TeamService;

class DeleteTeamAction
{
    public function __construct(protected TeamService $teamService) {}

    /**
     * run action
     */
    public function handle(Team $team): bool
    {
        return $this->teamService->delete($team);
    }
}
