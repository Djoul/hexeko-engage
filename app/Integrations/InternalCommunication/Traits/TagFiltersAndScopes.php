<?php

namespace App\Integrations\InternalCommunication\Traits;

use App\Integrations\InternalCommunication\Models\Tag;
use Illuminate\Database\Eloquent\Builder;

trait TagFiltersAndScopes
{
    /**
     * Scope a query to only include tags for a specific financer.
     *
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeForFinancer(Builder $query, string $financerId): Builder
    {
        return $query->where('financer_id', $financerId);
    }

    /**
     * Scope a query to search tags by label.
     *
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeSearchLabel(Builder $query, string $search): Builder
    {
        return $query->where('label', 'ILIKE', "%{$search}%");
    }

    /**
     * Scope a query to only include tags used in articles.
     *
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeUsedInArticles(Builder $query): Builder
    {
        return $query->whereHas('articles');
    }

    /**
     * Scope a query to only include tags not used in any article.
     *
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    public function scopeUnused(Builder $query): Builder
    {
        return $query->whereDoesntHave('articles');
    }
}
