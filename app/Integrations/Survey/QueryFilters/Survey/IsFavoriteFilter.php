<?php

namespace App\Integrations\Survey\QueryFilters\Survey;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IsFavoriteFilter extends AbstractFilter
{
    /**
     * Filter by favorite status.
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

        if ($isFavorite) {
            return $query->whereHas('favorites', function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId);
            });
        }

        return $query;
    }
}
