<?php

namespace App\QueryFilters\ModelSpecific\Financer;

use App\QueryFilters\AbstractFilter;
use Arr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DivisionIdFilter extends AbstractFilter
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
        $accessibleDivisions = authorizationContext()->divisionIds();

        $values = Arr::wrap($value);

        if (is_string($value)) {
            $values = explode(',', $value);
        }

        $intersect = array_intersect($values, $accessibleDivisions);

        // if $values contains value that are not in  $accessibleDivisions throw 422
        if ($intersect !== $values) {
            abort(401, 'wrong division id sended');
        }

        return authorizationContext()->scopeForDivision($query, $values);

    }
}
