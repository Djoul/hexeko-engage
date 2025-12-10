<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\Shared\TextFilter;

class LogoUrlFilter extends TextFilter
{
    /**
     * Filters links according to the provided logo_url (partial search, case insensitive).
     */
    protected function getColumnName(): string
    {
        return 'logo_url';
    }
}
