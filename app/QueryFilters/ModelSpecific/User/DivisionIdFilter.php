<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\Models\Financer;
use App\QueryFilters\AbstractFilter;
use Arr;
use Context;
use Illuminate\Database\Eloquent\Builder;

class DivisionIdFilter extends AbstractFilter
{
    /**
     * Filter users by Division ID through the financers relationship.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        $values = $this->normalizeValues($value);

        if ($values === []) {
            return $query;
        }

        $accessibleDivisions = authorizationContext()->divisionIds();
        $accessibleFinancers = authorizationContext()->financerIds();

        $intersect = array_intersect($values, $accessibleDivisions);

        // if $values contains value that are not in $accessibleDivisions throw 422
        if ($intersect !== $values) {
            abort(401, 'wrong division id sended');
        }

        // Get financers that are both in the requested divisions AND accessible to the user
        $financersInDivisions = Financer::whereIn('division_id', $values)->pluck('id')->toArray();
        $allowedFinancers = array_intersect($financersInDivisions, $accessibleFinancers);

        // Store the filtered financer IDs in context
        Context::add('financer_ids', $allowedFinancers);

        // Filter users that belong to these divisions AND accessible financers
        $query = authorizationContext()->scopeForDivision($query, $values);

        return $query->whereHas('financers', function (Builder $q) use ($allowedFinancers): void {
            $q->whereIn('financers.id', $allowedFinancers);
        });
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
