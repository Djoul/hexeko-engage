<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\QueryFilters\Questionnaire\FinancerIdFilter;
use App\Integrations\Survey\QueryFilters\Questionnaire\GlobalSearchFilter;
use App\Integrations\Survey\QueryFilters\Questionnaire\IsDefaultFilter;
use App\Integrations\Survey\QueryFilters\Questionnaire\StatusFilter;
use App\Integrations\Survey\QueryFilters\Questionnaire\TypeFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\OnlyArchivedFilter;
use App\QueryFilters\Shared\WithArchivedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class QuestionnairePipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Question-specific filters
        TypeFilter::class,
        IsDefaultFilter::class,
        FinancerIdFilter::class,
        StatusFilter::class,
        GlobalSearchFilter::class,

        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
        WithArchivedFilter::class,
        OnlyArchivedFilter::class,
    ];

    /**
     * @param  Builder<Questionnaire>  $query
     * @return Builder<Questionnaire>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Questionnaire> $result */
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
