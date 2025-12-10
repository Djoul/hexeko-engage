<?php

namespace App\Integrations\Survey\QueryFilters\Survey;

use App\Integrations\Survey\Enums\UserSurveyStatusEnum;
use App\Models\User;
use App\QueryFilters\AbstractFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserStatusFilter extends AbstractFilter
{
    /**
     * Indicate that this filter supports array parameters.
     */
    protected function supportsArrayParams(): bool
    {
        return true;
    }

    /**
     * Filter by user status (open, ongoing, completed).
     * Supports both single value and array of values.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        if ($value === null) {
            return $query;
        }

        if (! Auth::check()) {
            return $query;
        }

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query;
        }

        // Handle array of statuses
        if (is_array($value)) {
            return $query->where(function (Builder $query) use ($value, $user): void {
                foreach ($value as $status) {
                    if (! is_string($status)) {
                        continue;
                    }

                    $query->orWhere(function (Builder $subQuery) use ($status, $user): void {
                        $this->applyStatusFilter($subQuery, $status, $user);
                    });
                }
            });
        }

        // Handle single status (string)
        if (is_string($value)) {
            return $this->applyStatusFilter($query, $value, $user);
        }

        return $query;
    }

    /**
     * Apply filter for a single status value.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyStatusFilter(Builder $query, string $status, User $user): Builder
    {
        if ($status === UserSurveyStatusEnum::OPEN) {
            return $query->whereDoesntHave('submissions', function (Builder $subQuery) use ($user): void {
                $subQuery->where('user_id', $user->id);
            });
        }

        if ($status === UserSurveyStatusEnum::ONGOING) {
            return $query->whereHas('submissions', function (Builder $subQuery) use ($user): void {
                $subQuery->where('user_id', $user->id)
                    ->whereNull('completed_at');
            });
        }

        if ($status === UserSurveyStatusEnum::COMPLETED) {
            return $query->whereHas('submissions', function (Builder $subQuery) use ($user): void {
                $subQuery->where('user_id', $user->id)
                    ->whereNotNull('completed_at');
            });
        }

        return $query;
    }
}
