<?php

namespace App\Models\Traits\Financer;

use App\Models\Financer;
use App\Pipelines\FilterPipelines\FinancerPipeline;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait FinancerFiltersAndScopes
{
    // Champs de tri autorisés pour le pipeline OrderBy
    /**
     * @var array<string>
     */
    public static array $sortable = [
        'name', 'external_id', 'created_at',
    ];

    // Champ de tri par défaut
    public static string $defaultSortField = 'created_at';

    public static string $defaultSortDirection = 'desc';

    protected static function booted()
    {
        //        static::addGlobalScope(new UserRelatedFinancerScope);

        static::creating(function (Financer $financer): void {
            if (empty($financer->available_languages)) {
                $financer->available_languages = [$financer->division->language];
            }
        });

        static::saving(function (Financer $financer): void {
            if (empty($financer->available_languages)) {
                $financer->available_languages = [$financer->division->language];
            }
        });
    }

    /**
     * Apply the UserPipeline to the given query.
     *
     * @param  Builder<Financer>  $query
     * @return Builder<Financer>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(FinancerPipeline::class)->apply($query);
    }
}
