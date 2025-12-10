<?php

declare(strict_types=1);

namespace App\Integrations\Survey\QueryFilters\Theme;

use App\Integrations\Survey\Models\Theme;
use App\QueryFilters\Contracts\Filter;
use App\Traits\SearchableFilter;
use Illuminate\Database\Eloquent\Model;

class GlobalSearchFilter implements Filter
{
    use SearchableFilter;

    /**
     * Get the model instance.
     */
    protected function getModel(): Model
    {
        return new Theme;
    }
}
