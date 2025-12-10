<?php

namespace App\Integrations\Survey\QueryFilters\Survey;

use App\QueryFilters\Shared\TextFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StatusFilter extends TextFilter
{
    /**
     * Filter by status(es). Supports multiple statuses separated by comma.
     * Example: status=draft,published,archived will filter surveys with any of these statuses.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if (! is_string($value) || trim($value) === '') {
            return $query;
        }

        // Split by comma and clean up statuses
        $statuses = array_map('trim', explode(',', $value));
        $statuses = array_filter($statuses, fn (string $status): bool => $status !== '');

        if ($statuses === []) {
            return $query;
        }

        // If only one status, use the existing logic
        if (count($statuses) === 1) {
            return $this->applySingleStatus($query, $statuses[0]);
        }

        // Multiple statuses: apply each status filter and combine with OR
        return $query->where(function (Builder $query) use ($statuses): void {
            foreach ($statuses as $status) {
                $query->orWhere(function (Builder $subQuery) use ($status): void {
                    $this->applySingleStatus($subQuery, $status);
                });
            }
        });
    }

    /**
     * Apply filter for a single status value.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySingleStatus(Builder $query, string $status): Builder
    {
        if ($status === 'draft') {
            return $query->draft();
        }

        if ($status === 'scheduled') {
            return $query->scheduled();
        }

        if ($status === 'new') {
            return $query->new();
        }

        if ($status === 'active') {
            return $query->active();
        }

        if ($status === 'closed') {
            return $query->closed();
        }

        if ($status === 'archived') {
            return $query->onlyArchived();
        }

        return $query->where('status', $status);
    }
}
