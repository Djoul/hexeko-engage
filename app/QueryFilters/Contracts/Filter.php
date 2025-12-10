<?php

namespace App\QueryFilters\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Builder;

interface Filter
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(Builder<TModel>): mixed  $next
     */
    public function handle(Builder $query, Closure $next): mixed;
}
