<?php

namespace App\Integrations\HRTools\QueryFilters\ModelSpecific\Link;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;

class DeletedAtFilter extends AbstractFilter
{
    /**
     * Filters links by soft delete status.
     *
     * Accepts :
     * - 'null' → resources not deleted
     * - 'not_null' → resources deleted
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            return $query;
        }

        return match ($value) {
            'null' => $query->whereNull('deleted_at'),
            'not_null' => $query->whereNotNull('deleted_at'),
            default => $query,
        };
    }
}
