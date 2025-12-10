<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CreatedAtFilter extends AbstractFilter
{
    /**
     * Filter by created_at date (exact date match).
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

        return $query->whereDate('created_at', $value);
    }
}
