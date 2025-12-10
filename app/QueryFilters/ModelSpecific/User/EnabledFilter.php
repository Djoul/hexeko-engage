<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EnabledFilter extends AbstractFilter
{
    /**
     * Filter by enabled status (boolean).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) || ! in_array(strtolower($value), ['true', 'false', '0', '1'])) {
            return $query;
        }

        $enabled = in_array(strtolower($value), ['true', '1']);

        return $query->where('enabled', $enabled);
    }
}
