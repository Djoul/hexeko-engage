<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\Shared\TextFilter;

class TitleFilter extends TextFilter
{
    /**
     * Filters links by title (partial search, case insensitive).
     */
    protected function getColumnName(): string
    {
        return 'title';
    }
}
