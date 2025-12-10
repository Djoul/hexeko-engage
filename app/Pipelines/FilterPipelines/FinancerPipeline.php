<?php

namespace App\Pipelines\FilterPipelines;

use App\Models\Financer;
use App\Pipelines\SortApplier;
use App\QueryFilters\ModelSpecific\Financer\DivisionIdFilter;
use App\QueryFilters\ModelSpecific\Financer\GlobalSearchFilter;
use App\QueryFilters\ModelSpecific\Financer\IbanFilter;
use App\QueryFilters\ModelSpecific\Financer\IdFilter;
use App\QueryFilters\ModelSpecific\Financer\RegistrationCountryFilter;
use App\QueryFilters\ModelSpecific\Financer\RegistrationNumberFilter;
use App\QueryFilters\ModelSpecific\Financer\RepresentativeIdFilter;
use App\QueryFilters\ModelSpecific\Financer\StatusFilter;
use App\QueryFilters\ModelSpecific\Financer\VatNumberFilter;
use App\QueryFilters\ModelSpecific\Financer\WebsiteFilter;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\NameFilter;
use App\QueryFilters\Shared\RemarksFilter;
use App\QueryFilters\Shared\TimezoneFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class FinancerPipeline
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

        // Financer-specific filters
        IdFilter::class,
        StatusFilter::class,
        RegistrationNumberFilter::class,
        RegistrationCountryFilter::class,
        WebsiteFilter::class,
        IbanFilter::class,
        VatNumberFilter::class,
        RepresentativeIdFilter::class,
        DivisionIdFilter::class,
    ];

    /**
     * Apply filters and sorting to query
     *
     * @param  Builder<Financer>  $query
     * @return Builder<Financer>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Financer> $result */
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
