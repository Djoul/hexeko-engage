<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\Shared\TextFilter;

class ApiEndpointFilter extends TextFilter
{
    protected function getColumnName(): string
    {
        return 'api_endpoint';
    }
}
