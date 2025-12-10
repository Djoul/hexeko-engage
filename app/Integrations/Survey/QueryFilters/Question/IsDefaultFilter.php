<?php

namespace App\Integrations\Survey\QueryFilters\Question;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IsDefaultFilter extends AbstractFilter
{
    /**
     * Filter by is default (boolean).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) || ! in_array(strtolower($value), ['true', 'false', '0', '1'])) {
            return $query;
        }

        $isDefault = in_array(strtolower($value), ['true', '1']);

        return $query->where('is_default', $isDefault);
    }
}
