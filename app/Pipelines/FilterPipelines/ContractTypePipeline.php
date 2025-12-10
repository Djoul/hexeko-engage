<?php

namespace App\Pipelines\FilterPipelines;

use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\DeletedAtFilter;
use App\Models\ContractType;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\CreatedAtFilter;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\NameFilter;
use App\QueryFilters\Shared\UpdatedAtFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class ContractTypePipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Shared generic filters
        NameFilter::class,
        DateFromFilter::class,
        DateToFilter::class,
        CreatedAtFilter::class,
        UpdatedAtFilter::class,
        DeletedAtFilter::class,
    ];

    /**
     * Apply filters to query
     *
     * @param  Builder<ContractType>  $query
     * @return Builder<ContractType>
     */
    public function apply($query): Builder
    {
        /** @var Builder<ContractType> $result */
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
