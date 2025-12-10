<?php

namespace App\Pipelines\FilterPipelines;

use App\Models\Division;
use App\Pipelines\SortApplier;
use App\QueryFilters\ModelSpecific\Division\CurrencyFilter;
use App\QueryFilters\ModelSpecific\Division\GlobalSearchFilter;
use App\QueryFilters\ModelSpecific\Division\IdFilter;
use App\QueryFilters\ModelSpecific\Division\StatusFilter;
use App\QueryFilters\Shared\CountryFilter;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\LanguageFilter;
use App\QueryFilters\Shared\NameFilter;
use App\QueryFilters\Shared\RemarksFilter;
use App\QueryFilters\Shared\TimezoneFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class DivisionPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Global search filter (must be first)
        GlobalSearchFilter::class,

        // Shared generic filters
        NameFilter::class,
        RemarksFilter::class,
        DateFromFilter::class,
        DateToFilter::class,
        TimezoneFilter::class,
        LanguageFilter::class,

        // Division-specific filters
        IdFilter::class,
        StatusFilter::class,
        CountryFilter::class,
        CurrencyFilter::class,
    ];

    /**
     * Apply filters to query
     *
     * @param  Builder<Division>  $query
     * @return Builder<Division>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Division> $result */
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
