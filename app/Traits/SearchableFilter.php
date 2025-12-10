<?php

declare(strict_types=1);

namespace App\Traits;

use App\Contracts\Searchable;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Trait for implementing global search functionality in query filters.
 *
 * This trait provides a reusable implementation for searching across
 * model fields and relations. It requires the model to implement
 * the Searchable interface.
 *
 * @see \App\Contracts\Searchable
 */
trait SearchableFilter
{
    /**
     * Handle the search filter.
     *
     * Applies search criteria to the query builder if a valid search term
     * is provided. Searches are case-insensitive using PostgreSQL ILIKE.
     *
     * @param  Builder<Model>  $builder  The query builder instance
     * @param  Closure  $next  The next filter in the pipeline
     * @return mixed The modified query builder
     */
    public function handle(Builder $builder, Closure $next): mixed
    {
        $searchTerm = request()->get('search');

        if (empty($searchTerm) || ! is_string($searchTerm) || strlen($searchTerm) < 2) {
            return $next($builder);
        }

        $model = $builder->getModel();

        if (! $model instanceof Searchable) {
            return $next($builder);
        }

        // Invalidate search cache
        $this->invalidateSearchCache($model);

        // Apply search filter
        $builder->where(function (Builder $query) use ($model, $searchTerm): void {
            // Get model class for static method calls
            $modelClass = get_class($model);

            // Search in direct fields
            foreach ($model->getSearchableFields() as $field) {
                // Check if model has custom searchable expression
                $customExpression = null;
                if (method_exists($modelClass, 'getSearchableExpression')) {
                    $customExpression = $modelClass::getSearchableExpression($field);
                }

                if ($customExpression !== null) {
                    // Use custom expression for virtual fields
                    $query->orWhereRaw("{$customExpression} ILIKE ?", ["%{$searchTerm}%"]);
                } else {
                    // Use standard column search
                    $query->orWhere($field, 'ILIKE', "%{$searchTerm}%");
                }
            }

            // Search in relations
            foreach ($model->getSearchableRelations() as $relation => $fields) {
                $query->orWhereHas($relation, function (Builder $q) use ($fields, $searchTerm): void {
                    $q->where(function (Builder $subQuery) use ($fields, $searchTerm): void {
                        foreach ($fields as $field) {
                            $subQuery->orWhere($field, 'ILIKE', "%{$searchTerm}%");
                        }
                    });
                });
            }
        });

        return $next($builder);
    }

    /**
     * Invalidate the search cache for the model.
     */
    protected function invalidateSearchCache(Model $model): void
    {
        if (method_exists($model, 'getCacheTag')) {
            Cache::tags([$model->getCacheTag(), 'search'])->flush();
        }
    }

    /**
     * Get the model instance.
     */
    abstract protected function getModel(): Model;
}
