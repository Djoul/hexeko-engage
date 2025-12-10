<?php

namespace App\QueryFilters\Shared;

class NameFilter extends TranslatableTextFilter
{
    /**
     * Filter by name (partial search, case insensitive).
     * This filter is designed to work with JSON columns that store translations.
     */
    protected function getColumnName(): string
    {
        return 'name';
    }
}
