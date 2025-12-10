<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\QueryFilters\Submission\FinancerIdFilter;
use App\Integrations\Survey\QueryFilters\Submission\SurveyIdFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\CreatedAtFilter;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\DeletedAtFilter;
use App\QueryFilters\Shared\UpdatedAtFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class SubmissionPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Submission-specific filters
        SurveyIdFilter::class,
        FinancerIdFilter::class,

        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
        CreatedAtFilter::class,
        UpdatedAtFilter::class,
        DeletedAtFilter::class,
    ];

    /**
     * @param  Builder<Submission>  $query
     * @return Builder<Submission>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Submission> $result */
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
