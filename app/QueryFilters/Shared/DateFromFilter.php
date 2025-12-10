<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DateFromFilter extends AbstractFilter
{
    /**
     * Filters records where the date is greater than or equal to the specified date.
     * The date field can be specified via the 'date_from_fields' parameter (default: created_at).
     * Example: ?date_from=2023-01-01&date_from_fields[]=created_at&date_from_fields[]=updated_at
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

        // Retrieve the date field(s) from the query parameter, if specified
        $dateFields = request()->query('date_from_fields');

        // List of valid date fields
        $validDateFields = ['created_at', 'updated_at', 'deleted_at'];

        // If no field is specified or if the field is not valid, use created_at by default
        if (empty($dateFields)) {
            return $query->where('created_at', '>=', $value);
        }

        // If a single field is specified (string)
        if (is_string($dateFields)) {
            $dateField = in_array($dateFields, $validDateFields) ? $dateFields : 'created_at';

            return $query->where($dateField, '>=', $value);
        }

        // If multiple fields are specified (array)
        if (is_array($dateFields)) {
            $query->where(function (Builder $query) use ($dateFields, $validDateFields, $value): void {
                foreach ($dateFields as $field) {
                    if (is_string($field) && in_array($field, $validDateFields)) {
                        $query->orWhere($field, '>=', $value);
                    }
                }
            });

            return $query;
        }

        return $query->where('created_at', '>=', $value);
    }
}
