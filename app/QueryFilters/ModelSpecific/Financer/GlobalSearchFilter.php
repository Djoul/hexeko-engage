<?php

declare(strict_types=1);

namespace App\QueryFilters\ModelSpecific\Financer;

use App\Models\Financer;
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
        return new Financer;
    }
}
