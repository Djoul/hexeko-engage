<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnlyArchivedFilter extends AbstractFilter
{
    /**
     * Filters records by only archived (boolean).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! in_array(strtolower($value), ['true', 'false', '0', '1'])) {
            return $query;
        }

        if (strtolower($value) === 'true' || $value === '1') {
            return $query->onlyArchived();
        }

        return $query;
    }
}
