<?php

namespace App\Integrations\Survey\QueryFilters\Survey;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DateFromFilter extends AbstractFilter
{
    /**
     * Filter by date within period.
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

        return $query->where('starts_at', '>=', $value);
    }
}
