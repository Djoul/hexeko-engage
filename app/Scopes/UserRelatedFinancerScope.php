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
class UserRelatedFinancerScope implements Scope
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

        $builder->whereHas('users', function (Builder $query) use ($auth): void {
            $query->where('users.id', $auth->id);
        });
    }
}
