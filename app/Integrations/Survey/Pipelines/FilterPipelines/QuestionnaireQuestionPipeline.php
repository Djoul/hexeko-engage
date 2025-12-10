<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Question;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class QuestionnaireQuestionPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
    ];

    /**
     * Pivot columns that can be sorted on
     *
     * @var array<string>
     */
    protected array $pivotSortable = ['position'];

    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Question> $result */
        $result = app(Pipeline::class)
            ->send($query)
            ->through($this->filters)
            ->thenReturn();

        $modelClass = get_class($result->getModel());
        $sortable = $modelClass::$sortable ?? [];

        // For questionnaire questions, default to sorting by position (pivot column)
        $defaultField = 'position';
        $defaultDirection = 'asc';

        return SortApplier::apply($result, $sortable, $defaultField, $defaultDirection);
    }
}
