<?php

namespace App\Integrations\Survey\QueryFilters\Submission;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SurveyIdFilter extends AbstractFilter
{
    /**
     * Filter by survey id (exact match).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        return $query->where('survey_id', $value);
    }
}
