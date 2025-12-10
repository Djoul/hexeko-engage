<?php

namespace App\Integrations\InternalCommunication\Pipelines\FilterPipelines;

use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class ArticleUserPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Shared filters
        DateFromFilter::class,
        DateToFilter::class,
    ];

    /**
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function apply($query): Builder
    {
        /** @var Builder<Article> $result */
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
