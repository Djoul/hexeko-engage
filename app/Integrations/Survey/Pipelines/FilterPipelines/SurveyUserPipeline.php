<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\SurveyUser;
use App\Pipelines\SortApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;

class SurveyUserPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [];

    /**
     * Pivot columns that can be sorted on
     *
     * @var array<string>
     */
    protected array $pivotSortable = ['last_name'];

    /**
     * @param  Builder<Model>  $query
     * @return Builder<SurveyUser>
     */
    public function apply($query): Builder
    {
        /** @var Builder<SurveyUser> $query */
        /** @var Builder<SurveyUser> $result */
        $result = app(Pipeline::class)
            ->send($query)
            ->through($this->filters)
            ->thenReturn();

        $modelClass = get_class($result->getModel());
        $sortable = $modelClass::$sortable ?? [];

        // For survey users, default to sorting by last name (pivot column)
        $defaultField = 'last_name';
        $defaultDirection = 'asc';

        return SortApplier::apply($result, $sortable, $defaultField, $defaultDirection);
    }
}
