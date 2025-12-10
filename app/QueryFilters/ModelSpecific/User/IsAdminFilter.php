<?php

namespace App\QueryFilters\ModelSpecific\User;

use App\Enums\IDP\RoleDefaults;
use App\Models\User;
use App\QueryFilters\AbstractFilter;
use Context;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Validator;

class IsAdminFilter extends AbstractFilter
{
    /**
     * Filter users by admin status.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyFilter(Builder $query, mixed $value): Builder
    {

        /**
         * Validate that the value is one of: true, false, 0, 1
         *
         * @throws ValidationException
         */
        Validator::validate(['is_admin' => $value], [
            'is_admin' => ['required', 'string', 'in:true,false,0,1'],
        ]);

        // Get financer context: use financer_ids if set (from FinancerIdFilter), otherwise authorization context
        $contextFinancers = Context::get('financer_ids') ?? authorizationContext()->financerIds();

        $isAdmin = in_array(strtolower($value), ['true', '1']);
        if ($isAdmin) {
            // Return users having at least one admin role from either:
            // 1. Spatie roles (model_has_roles) != BENEFICIARY
            // 2. financer_user.role != 'beneficiary' IN CONTEXT FINANCERS
            return $query->where(function ($query) use ($contextFinancers): void {
                // Has Spatie role != BENEFICIARY
                $query->whereExists(function ($subQuery): void {
                    $subQuery->selectRaw(1)
                        ->from('model_has_roles')
                        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->whereColumn('model_has_roles.model_uuid', 'users.id')
                        ->where('model_has_roles.model_type', User::class)
                        ->where('roles.name', '!=', RoleDefaults::BENEFICIARY)
                        ->where('roles.guard_name', 'api');
                })
                    // OR has pivot role != 'beneficiary' in context financers
                    ->orWhereHas('financers', function ($financerQuery) use ($contextFinancers): void {
                        $financerQuery->where('financer_user.role', '!=', 'beneficiary');

                        // Only check within context financers
                        if (! empty($contextFinancers)) {
                            $financerQuery->whereIn('financer_user.financer_id', $contextFinancers);
                        }
                    });
            });
        }

        // Return users having ONLY BENEFICIARY role (or no role) in BOTH systems
        // IMPORTANT: Check pivot roles ONLY in context financers
        return $query->where(function ($query) use ($contextFinancers): void {
            // Check Spatie roles: must be BENEFICIARY only or none
            $query->where(function ($spatieQuery): void {
                $spatieQuery->whereDoesntHave('roles')
                    ->orWhere(function ($subQuery): void {
                        $subQuery->whereHas('roles', function ($roleQuery): void {
                            $roleQuery->where('name', RoleDefaults::BENEFICIARY)
                                ->where('guard_name', 'api');
                        })->whereDoesntHave('roles', function ($roleQuery): void {
                            $roleQuery->where('name', '!=', RoleDefaults::BENEFICIARY)
                                ->where('guard_name', 'api');
                        });
                    });
            })
                // AND check pivot roles: must be 'beneficiary' only in context financers
                ->where(function ($pivotQuery) use ($contextFinancers): void {
                    // No financers at all OR no admin roles in context financers
                    $pivotQuery->whereDoesntHave('financers')
                        ->orWhereDoesntHave('financers', function ($financerQuery) use ($contextFinancers): void {
                            $financerQuery->where('financer_user.role', '!=', 'beneficiary');

                            // Only check within context financers
                            if (! empty($contextFinancers)) {
                                $financerQuery->whereIn('financer_user.financer_id', $contextFinancers);
                            }
                        });
                });
        });
    }
}
