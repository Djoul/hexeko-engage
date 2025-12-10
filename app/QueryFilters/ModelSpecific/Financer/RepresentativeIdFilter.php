<?php

namespace App\QueryFilters\ModelSpecific\Financer;

use App\QueryFilters\Shared\IdFilter;

class RepresentativeIdFilter extends IdFilter
{
    /**
     * Override to use 'representative_id' column instead of 'id'.
     */
    protected function getColumnName(): string
    {
        return 'representative_id';
    }
}
