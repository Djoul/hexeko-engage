<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\Shared\TranslatableTextFilter;

class UrlFilter extends TranslatableTextFilter
{
    /**
     * Filter links by URL (partial search, case insensitive).
     * This filter is designed to work with JSON columns that store translations.
     */
    protected function getColumnName(): string
    {
        return 'url';
    }
}
