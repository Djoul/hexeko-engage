<?php

namespace App\Integrations\Survey\QueryFilters\Theme;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FinancerIdFilter extends AbstractFilter
{
    /**
     * Filter by financer id (exact match).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        return $query->where(function ($q) use ($value): void {
            $q->where('financer_id', $value);
            $q->orWhereNull('financer_id');
        });
    }
}
