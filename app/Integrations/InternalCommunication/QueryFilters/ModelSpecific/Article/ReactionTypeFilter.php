<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\QueryFilters\ModelSpecific\Article;

use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReactionTypeFilter extends AbstractFilter
{
    /**
     * Apply the filter to filter articles by reaction type.
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

        return $query->whereHas('interactions', function (Builder $query) use ($value): void {
            $query->where('reaction', $value)
                ->orWhereHas('translation', function (Builder $query) use ($value): void {
                    $query->whereHas('interactions', function (Builder $query) use ($value): void {
                        $query->where('reaction', $value);
                    });
                });
        });
    }
}
