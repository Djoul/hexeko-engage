<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\QueryFilters\Survey\DateFromFilter;
use App\Integrations\Survey\QueryFilters\Survey\DateToFilter;
use App\Integrations\Survey\QueryFilters\Survey\FinancerIdFilter;
use App\Integrations\Survey\QueryFilters\Survey\GlobalSearchFilter;
use App\Integrations\Survey\QueryFilters\Survey\IsFavoriteFilter;
use App\Integrations\Survey\QueryFilters\Survey\StatusFilter;
use App\Integrations\Survey\QueryFilters\Survey\UserStatusFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\CreatedAtFilter;
use App\QueryFilters\Shared\DeletedAtFilter;
use App\QueryFilters\Shared\OnlyArchivedFilter;
use App\QueryFilters\Shared\UpdatedAtFilter;
use App\QueryFilters\Shared\WithArchivedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class SurveyPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Survey-specific filters
        StatusFilter::class,
        UserStatusFilter::class,
        FinancerIdFilter::class,
        GlobalSearchFilter::class,
        IsFavoriteFilter::class,
        DateFromFilter::class,
        DateToFilter::class,

        // Shared filters
        WithArchivedFilter::class,
        OnlyArchivedFilter::class,
        CreatedAtFilter::class,
        UpdatedAtFilter::class,
        DeletedAtFilter::class,
    ];

    /**
     * @param  Builder<Survey>  $query
     * @return Builder<Survey>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Survey> $result */
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
