<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\QueryFilters\Question\IsDefaultFilter;
use App\Integrations\Survey\QueryFilters\Question\TypeFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class ThemeQuestionPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Question-specific filters
        TypeFilter::class,
        IsDefaultFilter::class,

        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
    ];

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
        $defaultField = $modelClass::$defaultSortField ?? 'created_at';
        $defaultDirection = $modelClass::$defaultSortDirection ?? 'desc';

        return SortApplier::apply($result, $sortable, $defaultField, $defaultDirection);
    }
}
