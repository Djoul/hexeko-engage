<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RoleFilter extends AbstractFilter
{
    /**
     * Filter users by role.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) || empty($value)) {
            return $query;
        }

        return $query->whereHas('roles', function (Builder $query) use ($value): void {
            $query->where('name', $value);
        });
    }
}
