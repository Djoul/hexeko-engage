<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\Shared\TextFilter;

class FrontEndpointFilter extends TextFilter
{
    /**
     * Override to use 'front_endpoint' column.
     */
    protected function getColumnName(): string
    {
        return 'front_endpoint';
    }
}
