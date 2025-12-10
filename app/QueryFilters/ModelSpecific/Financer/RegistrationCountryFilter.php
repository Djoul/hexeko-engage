<?php

namespace App\QueryFilters\ModelSpecific\Financer;

use App\QueryFilters\Shared\CountryFilter;
use Illuminate\Database\Eloquent\Builder;

class RegistrationCountryFilter extends CountryFilter
{
    /**
     * Retourne le nom de la colonne à utiliser pour le filtre.
     */
    protected function getColumnName(): string
    {
        return 'registration_country';
    }

    /**
     * Filters records by registration country using partial match.
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            return $query;
        }

        return $query->where($this->getColumnName(), 'ilike', '%'.$value.'%');
    }
}
