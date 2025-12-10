<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait PipeFiltrable
{
    /**
     * Apply filters from request query parameters.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function pipeFiltered(Builder $query): Builder
    {
        $modelName = class_basename($this);

        $pipelineClass = "\\App\\Pipelines\\{$modelName}Pipeline";

        if (class_exists($pipelineClass) && method_exists($pipelineClass, 'apply')) {
            return (new $pipelineClass)->apply($query);
        }

        return $query;
    }
}
