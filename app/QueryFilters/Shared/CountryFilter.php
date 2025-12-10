<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CountryFilter extends AbstractFilter
{
    /**
     * Filters records by country (exact match).
     *  By default, the filter searches the 'country' column.
     *  To use another column, extend this class and override the getColumnName() method.
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

        return $query->where($this->getColumnName(), $value);
    }

    /**
     * Retourne le nom de la colonne à utiliser pour le filtre.
     * Peut être surchargé dans les classes enfants pour utiliser une colonne différente.
     */
    protected function getColumnName(): string
    {
        return 'country';
    }
}
