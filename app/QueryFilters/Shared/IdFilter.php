<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IdFilter extends AbstractFilter
{
    /**
     * Filters records by ID (exact match).
     *  By default, the filter searches the 'id' column.
     *  To use another column, extend this class and override the getColumnName() method.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            return $query;
        }

        return $query->where($this->getColumnName(), $value);
    }

    /**
     * Returns the name of the column to be used for the filter.
     * Can be overloaded in child classes to use a different column.
     */
    protected function getColumnName(): string
    {
        return 'id';
    }
}
