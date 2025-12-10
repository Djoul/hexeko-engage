<?php

declare(strict_types=1);

namespace App\Integrations\Survey\QueryFilters\Survey;

use App\Integrations\Survey\Models\Survey;
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
        return new Survey;
    }
}
