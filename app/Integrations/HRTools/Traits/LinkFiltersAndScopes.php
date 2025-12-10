<?php

namespace App\Integrations\HRTools\Traits;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Pipelines\FilterPipelines\LinkPipeline;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait LinkFiltersAndScopes
{
    /**
     * Apply the LinkPipeline to the given query.
     *
     * @param  Builder<Link>  $query
     * @return Builder<Link>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(LinkPipeline::class)->apply($query);
    }
}
