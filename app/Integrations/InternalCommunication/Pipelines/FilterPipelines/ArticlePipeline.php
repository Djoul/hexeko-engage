<?php

namespace App\Integrations\InternalCommunication\Pipelines\FilterPipelines;

use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\AuthorIdFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\ContentFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\GlobalSearchFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\IsFavoriteFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\LanguageFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\PublishedFromFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\PublishedToFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\ReactionTypeFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\SegmentIdFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\StatusFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\TagsFilter;
use App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article\TitleFilter;
use App\Pipelines\SortApplier;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\FinancerIdFilter;
use App\QueryFilters\Shared\IdFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class ArticlePipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Filtres spécifiques au modèle Article
        GlobalSearchFilter::class,
        TitleFilter::class,
        ContentFilter::class,
        StatusFilter::class,
        SegmentIdFilter::class,
        TagsFilter::class,
        AuthorIdFilter::class,
        PublishedFromFilter::class,
        PublishedToFilter::class,
        ReactionTypeFilter::class,
        IsFavoriteFilter::class,
        LanguageFilter::class,
        //
        //        // Filtres partagés
        FinancerIdFilter::class,
        IdFilter::class,
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

        $defaultField = $modelClass::$defaultSortField ?? 'updated_at';
        $defaultDirection = $modelClass::$defaultSortDirection ?? 'desc';

        return SortApplier::apply($result, $sortable, $defaultField, $defaultDirection);
    }
}
