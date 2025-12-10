<?php

namespace App\Models;

use App\Enums\IDP\RoleDefaults;
use App\Exceptions\PermissionDeniedException;
use App\Traits\AuditableModel;
use Auth;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property string $id
 * @property string|null $team_id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_protected
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \App\Models\Role|null $use_factory
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static RoleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static Builder<static>|Role onlyTrashed()
 * @method static Builder<static>|Role permission($permissions, $without = false)
 * @method static Builder<static>|Role query()
 * @method static Builder<static>|Role whereCreatedAt($value)
 * @method static Builder<static>|Role whereDeletedAt($value)
 * @method static Builder<static>|Role whereGuardName($value)
 * @method static Builder<static>|Role whereId($value)
 * @method static Builder<static>|Role whereIsProtected($value)
 * @method static Builder<static>|Role whereName($value)
 * @method static Builder<static>|Role whereTeamId($value)
 * @method static Builder<static>|Role whereUpdatedAt($value)
 * @method static Builder<static>|Role withTrashed()
 * @method static Builder<static>|Role withoutPermission($permissions)
 * @method static Builder<static>|Role withoutTrashed()
 *
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 *
 * @mixin \Eloquent
 */
class Role extends SpatieRole implements Auditable
{
    use AuditableModel;
    use HasFactory;
    use HasUuids;
    use LogsActivity;
    use SoftDeletes;

    public $incrementing = false;

    public string $guard_name = 'api';

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
        'team_id' => 'string',
    ];

    protected $with = ['permissions'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logAll()
            ->useLogName(static::logName());
    }

    protected static function logName(): string
    {
        return 'role';
    }

    /**
     * @throws PermissionDeniedException
     */
    public function canBeModifiedByAuth(): void
    {
        $authUserRoles = Auth::user()?->getRoleNames()->toArray() ?? [];

        if (! RoleDefaults::canManageRole($authUserRoles, $this->name)) {
            abort(403, 'You do not have permission to modify this role');
        }
    }

    /**
     * @param  string|array<int, string>|\Illuminate\Support\Collection<int, string>  $permission
     *
     * @throws PermissionDeniedException
     */
    // @phpstan-ignore-next-line
    public function givePermissionTo(...$permission): void
    {
        parent::givePermissionTo($permission);

        activity(static::logName())
            ->performedOn($this)
            ->log('Permission assigned');
    }

    /**
     * @param  string|array<int, string>|\Illuminate\Support\Collection<int, string>  $permission
     */
    // @phpstan-ignore-next-line
    public function revokePermissionTo($permission): void
    {
        parent::revokePermissionTo($permission);

        activity(static::logName())
            ->performedOn($this)
            ->log("Permission revoked from role {$this->name}");
    }

    /**
     * @param  User  $user
     */
    public function assignUser($user): void
    {
        $user->assignRole($this);

        activity(static::logName())
            ->performedOn($this)
            ->log("Role {$this->name} assigned to user {$user->id}");
    }

    /**
     * @param  User  $user
     */
    public function revokeUser($user): void
    {
        $user->removeRole($this);

        activity(static::logName())
            ->performedOn($this)
            ->log("Role {$this->name} revoked from user {$user->id}");
    }
}
