<?php

declare(strict_types=1);

namespace App\QueryFilters\ModelSpecific\Division;

use App\Models\Division;
use App\Traits\SearchableFilter;
use Illuminate\Database\Eloquent\Model;

class GlobalSearchFilter
{
    use SearchableFilter;

    /**
     * Get the model instance.
     */
    protected function getModel(): Model
    {
        return new Division;
    }
}
