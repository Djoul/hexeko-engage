<?php

namespace App\Models\Traits\User;

use App\Enums\IDP\RoleDefaults;
use App\Models\User;
use App\Pipelines\FilterPipelines\UserPipeline;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait UserFiltersAndScopes
{
    /**
     * Champs de tri autorisés pour le pipeline OrderBy.
     *
     * @var array<string>
     */
    public static array $sortable = [
        'id', 'first_name', 'last_name', 'full_name', 'name', 'email', 'created_at', 'updated_at',
    ];

    public static string $defaultSortField = 'created_at';

    public static string $defaultSortDirection = 'desc';

    /**
     * Scope pour filtrer les résultats en fonction des financeurs liés à l'utilisateur connecté.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeUserRelated(Builder $query): Builder
    {
        if (app()->runningInConsole() || ! Auth::check()) {
            return $query;
        }

        /** @var User|null $auth */
        $auth = Auth::user();

        if (! $auth) {
            return $query;
        }

        if ($auth->hasAnyRole([RoleDefaults::GOD, RoleDefaults::HEXEKO_SUPER_ADMIN])) {
            return $query;
        }

        $financersId = cache()->remember('user_financers_'.$auth->id, 300, function () use ($auth) {
            return $auth->financers()->pluck('financers.id');
        });

        return $query->whereHas('financers', function (Builder $query) use ($financersId): void {
            $query->whereIn('financer_user.financer_id', $financersId);
        });
    }

    /**
     * Apply the UserPipeline to the given query.
     *
     * @param  Builder<User>  $query
     * @param  bool  $applySorting  Whether to apply SQL-level sorting (default true)
     * @return Builder<User>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query, bool $applySorting = true): Builder
    {
        return resolve(UserPipeline::class)->apply($query, $applySorting);
    }

    /**
     * Scope to filter only users with pending invitation status.
     * Equivalent to legacy InvitedUser records.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInvited(Builder $query): Builder
    {
        return $query->where('invitation_status', 'pending');
    }

    /**
     * Scope to filter only active users (not invited).
     * Excludes users with pending invitation status.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('invitation_status')
            ->orWhere('invitation_status', '!=', 'pending');
    }

    /**
     * Scope to filter users with pending invitations (alias for invited()).
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePendingInvitations(Builder $query): Builder
    {
        return $query->where('invitation_status', 'pending');
    }

    /**
     * Scope to filter users with expired invitations.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeExpiredInvitations(Builder $query): Builder
    {
        return $query->where('invitation_status', 'pending')
            ->where('invitation_expires_at', '<', now());
    }

    /**
     * Scope to filter users invited by a specific user.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeInvitedBy(Builder $query, string $inviterId): Builder
    {
        return $query->where('invited_by', $inviterId);
    }
}
