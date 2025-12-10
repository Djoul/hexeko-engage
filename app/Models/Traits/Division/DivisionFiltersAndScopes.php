<?php

declare(strict_types=1);

namespace App\Models\Traits\Division;

use App\Models\Division;
use App\Pipelines\FilterPipelines\DivisionPipeline;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait DivisionFiltersAndScopes
{
    /**
     * Apply the DivisionPipeline to the given query.
     *
     * @param  Builder<Division>  $query
     * @return Builder<Division>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(DivisionPipeline::class)->apply($query);
    }
}
