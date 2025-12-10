<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TranslatableTextFilter extends AbstractFilter
{
    /**
     * Filters records by a translatable text field (partial search, case insensitive).
     * This filter is designed to work with JSON columns that store translations.
     * By default, the filter searches in the column corresponding to the filter name.
     * To use another column, extend this class and override the getColumnName() method.
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

        // Use a more reliable approach for searching in JSON columns
        // This will search for the value in any language
        return $query->where(function ($query) use ($value): void {
            // Get the column name
            $column = $this->getColumnName();

            // Add a where clause that checks if the JSON column contains the value
            // We need to use a raw expression because we want to search in all languages
            $query->whereRaw("LOWER(CAST($column AS TEXT)) LIKE LOWER(?)", ['%'.$value.'%']);
        });
    }

    /**
     * Returns the name of the column to use for the filter.
     * By default, uses the filter name (without 'Filter' at the end).
     * Can be overridden in child classes to use a different column.
     */
    protected function getColumnName(): string
    {
        return $this->filterName();
    }
}
