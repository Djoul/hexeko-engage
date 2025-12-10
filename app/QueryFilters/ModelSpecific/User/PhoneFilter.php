<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PhoneFilter extends AbstractFilter
{
    /**
     * Filter by phone (partial search, case insensitive).
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

        return $query->where('phone', 'ilike', "%{$value}%");
    }
}
