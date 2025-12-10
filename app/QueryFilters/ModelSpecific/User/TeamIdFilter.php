<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\Shared\IdFilter;

class TeamIdFilter extends IdFilter
{
    /**
     * Override to use 'team_id' column instead of 'id'.
     */
    protected function getColumnName(): string
    {
        return 'team_id';
    }
}
