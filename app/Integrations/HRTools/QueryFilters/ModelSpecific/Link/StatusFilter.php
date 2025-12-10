<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;

class StatusFilter extends AbstractFilter
{
    /**
     * Filter links by status (strict equality).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $query;
        }

        return $query->where('status', '=', $value);
    }
}
