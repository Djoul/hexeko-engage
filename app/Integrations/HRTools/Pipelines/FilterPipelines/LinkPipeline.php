<?php

namespace App\Integrations\HRTools\Pipelines\FilterPipelines;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\ApiEndpointFilter;
use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\DeletedAtFilter;
use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\DescriptionFilter;
use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\FrontEndpointFilter;
use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\LogoUrlFilter;
use App\Integrations\HRTools\QueryFilters\ModelSpecific\Link\UrlFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\FinancerIdFilter;
use App\QueryFilters\Shared\NameFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class LinkPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Filtres spécifiques au modèle Link
        DescriptionFilter::class,
        UrlFilter::class,
        LogoUrlFilter::class,
        ApiEndpointFilter::class,
        FrontEndpointFilter::class,
        FinancerIdFilter::class,
        DeletedAtFilter::class,

        // Filtres partagés pour les dates
        NameFilter::class,
        DateFromFilter::class,
        DateToFilter::class,
    ];

    /**
     * @param  Builder<Link>  $query
     * @return Builder<Link>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Link> $result */
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
