<?php

declare(strict_types=1);

namespace App\QueryFilters\ModelSpecific\Financer;

use App\Enums\FinancerStatus;
use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StatusFilter extends AbstractFilter
{
    /**
     * Filter by status(es). Supports multiple statuses separated by comma.
     * Example: status=active,pending will filter WHERE status = 'active' OR status = 'pending'
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

        // Filter to only valid statuses and reindex array to prevent key preservation issues
        $validStatuses = array_values(
            array_filter($statuses, fn (string $status): bool => FinancerStatus::isValid($status))
        );

        if ($validStatuses === []) {
            return $query;
        }

        // If only one status, use simple where
        if (count($validStatuses) === 1) {
            return $query->where('status', $validStatuses[0]);
        }

        // Multiple statuses: use whereIn for better performance
        return $query->whereIn('status', $validStatuses);
    }
}
