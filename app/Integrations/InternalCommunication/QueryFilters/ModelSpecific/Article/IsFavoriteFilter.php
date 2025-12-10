<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IsFavoriteFilter extends AbstractFilter
{
    /**
     * Apply the filter to filter articles by favorite status.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            return $query;
        }

        $isFavorite = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        // Get user_id from query param or use authenticated user
        $userId = request()->query('user_id') ?? auth()->id();

        if (! $userId) {
            return $query;
        }

        // Combine both conditions in a SINGLE whereHas
        return $query->whereHas('interactions', function (Builder $query) use ($isFavorite, $userId): void {
            $query->where('is_favorite', $isFavorite)
                ->where('user_id', $userId);
        });
    }
}
