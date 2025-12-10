<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait HasDivisionScopes
{
    /**
     * Scope the query to the provided division ids.
     */
    public function scopeForDivision(Builder $query, array|string $divisionIds): Builder
    {
        $ids = Arr::wrap($divisionIds);

        return $query->whereHas($this->divisionRelationName(), function (Builder $divisionQuery) use ($ids): void {
            $divisionQuery->whereIn('divisions.id', $ids);
        });
    }

    /**
     * Override to customize the relation used by the scope.
     */
    protected function divisionRelationName(): string
    {
        return 'division';
    }
}
