<?php

namespace App\Integrations\Survey\QueryFilters\Question;

use App\Integrations\Survey\Models\Theme;
use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ThemeIdFilter extends AbstractFilter
{
    /**
     * Filter by theme id (exact match).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        $theme = Theme::where('id', $value)->first();
        if (! $theme) {
            return $query;
        }

        return $query->where('theme_id', $theme->id);
    }
}
