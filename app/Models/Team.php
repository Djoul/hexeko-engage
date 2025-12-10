<?php

namespace App\Models;

use App\Enums\IDP\TeamTypes;
use App\Models\Concerns\MarksAsDemo;
use App\Traits\AuditableModel;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TeamTypes|null $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team|null $use_factory
 *
 * @method static TeamFactory factory($count = null, $state = [])
 * @method static Builder<static>|Team newModelQuery()
 * @method static Builder<static>|Team newQuery()
 * @method static Builder<static>|Team onlyTrashed()
 * @method static Builder<static>|Team query()
 * @method static Builder<static>|Team whereCreatedAt($value)
 * @method static Builder<static>|Team whereDeletedAt($value)
 * @method static Builder<static>|Team whereId($value)
 * @method static Builder<static>|Team whereName($value)
 * @method static Builder<static>|Team whereSlug($value)
 * @method static Builder<static>|Team whereType($value)
 * @method static Builder<static>|Team whereUpdatedAt($value)
 * @method static Builder<static>|Team withTrashed()
 * @method static Builder<static>|Team withoutTrashed()
 *
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 *
 * @mixin \Eloquent
 */
class Team extends LoggableModel implements Auditable
{
    use AuditableModel;
    use HasFactory;
    use HasUuids;
    use MarksAsDemo;
    use SoftDeletes;

    protected $casts = [
        'type' => TeamTypes::class,
        'id' => 'string',
    ];

    protected static function logName(): string
    {
        return 'team';
    }
}
