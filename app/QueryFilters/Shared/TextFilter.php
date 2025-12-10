<?php

namespace App\QueryFilters\Shared;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TextFilter extends AbstractFilter
{
    /**
     * Filters records by a text field (partial search, case insensitive).
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

        return $query->where($this->getColumnName(), 'ilike', "%{$value}%");
    }

    /**
     * Retourne le nom de la colonne à utiliser pour le filtre.
     * Par défaut, utilise le nom du filtre (sans 'Filter' à la fin).
     * Peut être surchargé dans les classes enfants pour utiliser une colonne différente.
     */
    protected function getColumnName(): string
    {
        return $this->filterName();
    }
}
