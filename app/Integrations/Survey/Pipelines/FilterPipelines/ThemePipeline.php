<?php

namespace App\Integrations\Survey\Pipelines\FilterPipelines;

use App\Integrations\Survey\Models\Theme;
use App\Integrations\Survey\QueryFilters\Theme\DescriptionFilter;
use App\Integrations\Survey\QueryFilters\Theme\FinancerIdFilter;
use App\Integrations\Survey\QueryFilters\Theme\GlobalSearchFilter;
use App\Integrations\Survey\QueryFilters\Theme\IsDefaultFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\NameFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class ThemePipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Theme-specific filters
        DescriptionFilter::class,
        IsDefaultFilter::class,
        FinancerIdFilter::class,
        GlobalSearchFilter::class,

        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
        NameFilter::class,
    ];

    /**
     * @param  Builder<Theme>  $query
     * @return Builder<Theme>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Theme> $result */
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
