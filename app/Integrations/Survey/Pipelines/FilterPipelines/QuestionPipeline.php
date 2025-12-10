<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\QueryFilters\Question\FinancerIdFilter;
use App\Integrations\Survey\QueryFilters\Question\GlobalSearchFilter;
use App\Integrations\Survey\QueryFilters\Question\IsDefaultFilter;
use App\Integrations\Survey\QueryFilters\Question\ThemeIdFilter;
use App\Integrations\Survey\QueryFilters\Question\TypeFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\OnlyArchivedFilter;
use App\QueryFilters\Shared\WithArchivedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class QuestionPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Question-specific filters
        TypeFilter::class,
        IsDefaultFilter::class,
        ThemeIdFilter::class,
        FinancerIdFilter::class,
        GlobalSearchFilter::class,

        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
        WithArchivedFilter::class,
        OnlyArchivedFilter::class,
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
