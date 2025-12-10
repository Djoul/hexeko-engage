<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Question;
use App\Pipelines\SortApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pipeline\Pipeline;

class SurveyQuestionPipeline
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
    protected array $pivotSortable = ['position'];

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Question>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Question> $query */
        /** @var Builder<Question> $result */
        $result = app(Pipeline::class)
            ->send($query)
            ->through($this->filters)
            ->thenReturn();

        $modelClass = get_class($result->getModel());
        $sortable = $modelClass::$sortable ?? [];

        // For survey questions, default to sorting by position (pivot column)
        $defaultField = 'position';
        $defaultDirection = 'asc';

        return SortApplier::apply($result, $sortable, $defaultField, $defaultDirection);
    }
}
