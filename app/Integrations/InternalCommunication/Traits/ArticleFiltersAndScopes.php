<?php

namespace App\Integrations\InternalCommunication\Traits;

use App\Integrations\InternalCommunication\Pipelines\FilterPipelines\ArticlePipeline;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait ArticleFiltersAndScopes
{
    /**
     * Apply the ArticlePipeline to the given query.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(ArticlePipeline::class)->apply($query);
    }
}
