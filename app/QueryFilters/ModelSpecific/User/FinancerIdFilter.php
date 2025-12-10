<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\AbstractFilter;
use Arr;
use Context;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class FinancerIdFilter extends AbstractFilter
{
    /**
     * Filter users by Financer ID through the financer_user pivot table.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        $accessibleFinancers = authorizationContext()->financerIds();

        // If no accessible financers in context, skip filtering
        // This can happen in console/testing without proper middleware setup
        if (! is_array($accessibleFinancers)) {
            return $query;
        }

        $values = $this->normalizeValues($value);

        // If financer_id values are provided
        if ($values !== []) {
            // Validate against accessible financers
            $intersect = array_intersect($values, $accessibleFinancers);
            // if $values contains value that are not in $accessibleFinancers throw ValidationException
            if ($intersect !== $values) {
                throw ValidationException::withMessages([
                    'financer_id' => ['One or more financer IDs are not accessible to you.'],
                ]);
            }

            // If division_id was also provided, ensure financers are in both filters
            if (filled(request()->input('division_id'))) {
                $financersFromDivision = Context::get('financer_ids', []);
                if (! empty($financersFromDivision)) {
                    // Only keep financers that are in both the division AND the requested financer_ids
                    $values = array_intersect($values, $financersFromDivision);
                    if ($values === []) {
                        abort(422, 'No financers match both division_id and financer_id criteria');
                    }
                }
            }

            Context::add('financer_ids', $values);

            return $query->whereHas('financers', function (Builder $q) use ($values): void {
                $q->whereIn('financer_user.financer_id', $values);
            });
        }

        // If no financer_id provided but division_id is present, skip this filter
        // DivisionIdFilter already handles the filtering by division
        if (filled(request()->input('division_id'))) {
            // Don't apply additional financer filtering, let DivisionIdFilter handle it
            return $query;
        }

        // Default: use all accessible financers
        $values = authorizationContext()->financerIds();

        // If no accessible financers in context, return query unchanged
        // This can happen in console/testing without proper middleware setup
        if ($values === []) {
            return $query;
        }

        Context::add('financer_ids', $values);

        return $query->whereHas('financers', function (Builder $q) use ($values): void {
            $q->whereIn('financer_user.financer_id', $values);
        });
    }

    /**
     * @return array|string[]
     */
    protected function normalizeValues(mixed $value): array
    {
        if (! is_string($value)) {
            return Arr::wrap($value);
        }

        return explode(',', $value);
    }
}
