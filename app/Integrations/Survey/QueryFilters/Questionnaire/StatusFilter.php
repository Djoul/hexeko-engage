<?php

namespace App\Integrations\Survey\QueryFilters\Questionnaire;

use App\QueryFilters\Shared\TextFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StatusFilter extends TextFilter
{
    /**
     * Filter by status (exact match).
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

        return $query->where('status', $value);
    }
}
