<?php

namespace App\QueryFilters\ModelSpecific\Financer;

use App\QueryFilters\AbstractFilter;
use Arr;
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
        $accessibleFinancers = authorizationContext()->financerIds();

        $values = $this->normalizeValues($value);

        $intersect = array_intersect($values, $accessibleFinancers);

        // if $values contains value that are not in  $accessibleFinancers throw 422
        if ($intersect !== $values) {
            abort(401, 'wrong financer_id sended');
        }

        $values = $this->normalizeValues($value);

        if ($values === []) {
            return $query;
        }

        return $query->whereIn('id', $values);
    }

    /**
     * Normalize the input values to an array of IDs.
     */
    private function normalizeValues(mixed $value): array
    {
        if (is_string($value)) {
            return explode(',', $value);
        }

        return Arr::wrap($value);
    }
}
