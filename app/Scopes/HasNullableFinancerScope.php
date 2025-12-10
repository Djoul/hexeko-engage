<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class HasNullableFinancerScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $activeFinancerId = activeFinancerID();

        $builder->where(function ($q) use ($activeFinancerId): void {
            $q->whereNull('financer_id');
            if (! empty($activeFinancerId)) {
                $q->orWhere('financer_id', $activeFinancerId);
            }
        });
    }
}
