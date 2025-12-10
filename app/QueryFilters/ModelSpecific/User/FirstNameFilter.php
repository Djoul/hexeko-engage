<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\Shared\TextFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FirstNameFilter extends TextFilter
{
    /**
     * Filter by first_name (partial search, case insensitive).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
}
