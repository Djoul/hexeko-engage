<?php

namespace App\QueryFilters\Shared;

use App\Models\Financer;
use App\QueryFilters\AbstractFilter;
use Arr;
use Illuminate\Database\Eloquent\Builder;

class FinancerIdFilter extends AbstractFilter
{
    /**
     * Filter links by Financer ID (strict equality).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {

        if (! is_string($value) && filled(request()->input('division_id'))) {
            $value = Financer::whereIn('division_id', request()->input('division_id'))
                ->pluck('id')
                ->toArray();
        }
        if (! is_string($value) && ! filled(request()->input('division_id'))) {
            $value = activeFinancerID();
        }

        $values = $this->normalizeValues($value);

        if ($values === []) {
            return $query;
        }

        return $query->whereIn('financer_id', $values);
    }

    /**
     * @return array|string[]
     */
    protected function normalizeValues(mixed $value): array
    {

        if (! is_string($value)) {
            return Arr::wrap(activeFinancerID());
        }

        return explode(',', $value);
    }
}
