<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\QueryFilters\AbstractFilter;
use Context;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StatusFilter extends AbstractFilter
{
    /**
     * Filter by user status (active, inactive, invited).
     *
     * - 'active': Users with active=true in financer_user pivot
     * - 'inactive': Users with active=false in financer_user pivot
     * - 'invited': Users with invitation_status='pending'
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {
        $values = explode(',', (string) $value);
        $validStatuses = ['active', 'inactive', 'invited'];
        $filterByStatus = array_intersect($values, $validStatuses);

        if ($filterByStatus === []) {
            return $query;
        }

        $includeInvited = in_array('invited', $filterByStatus);
        $includeActive = in_array('active', $filterByStatus);
        $includeInactive = in_array('inactive', $filterByStatus);
        $includeRegular = $includeActive || $includeInactive;

        return $query->where(function (Builder $q) use ($includeInvited, $includeRegular, $includeActive, $includeInactive): void {
            // Include invited users (invitation_status='pending')
            if ($includeInvited) {
                $q->orWhere('invitation_status', 'pending');
            }

            // Include regular users (active/inactive based on pivot)
            if ($includeRegular) {
                $q->orWhere(function (Builder $subQuery) use ($includeActive, $includeInactive): void {
                    // Only non-pending users
                    $subQuery->where(function (Builder $invitationQuery): void {
                        $invitationQuery->whereNull('invitation_status')
                            ->orWhere('invitation_status', '!=', 'pending');
                    });

                    // Filter by financer pivot active status
                    $subQuery->whereHas('financers', function (Builder $financerQuery) use ($includeActive, $includeInactive): void {
                        $financerQuery->whereIn('financers.id', Context::get('financer_ids'))
                            ->where(function (Builder $pivotQuery) use ($includeActive, $includeInactive): void {
                                if ($includeActive) {
                                    $pivotQuery->orWhere('financer_user.active', true);
                                }
                                if ($includeInactive) {
                                    $pivotQuery->orWhere('financer_user.active', false);
                                }
                            });
                    });
                });
            }
        });
    }
}
