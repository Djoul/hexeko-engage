<?php

namespace App\Integrations\Survey\QueryFilters\Theme;

use App\QueryFilters\Shared\TranslatableTextFilter;

class DescriptionFilter extends TranslatableTextFilter
{
    /**
     * Filter By description (partial search, case insensitive).
     * This filter is designed to work with JSON columns that store translations.
     */
    protected function getColumnName(): string
    {
        return 'description';
    }
}
