<?php

namespace App\Scopes;

use App\Enums\IDP\RoleDefaults;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * @deprecated
 */
class UserRelatedDivisionScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() || ! Auth::check()) {
            return;
        }

        /** @var User $auth */
        $auth = Auth::user();

        if ($auth->hasAnyRole([RoleDefaults::GOD, RoleDefaults::HEXEKO_SUPER_ADMIN])) {
            return;
        }

        // Check if financers relation is already loaded to avoid extra queries
        if ($auth->relationLoaded('financers')) {
            $divisionIds = $auth->financers->pluck('division_id')->filter()->unique();
        } else {
            $divisionIds = $auth->financers()->pluck('division_id');
        }

        $builder->whereIn('id', $divisionIds);
    }
}
