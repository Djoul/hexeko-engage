<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\Cachable;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property array<string, string> $name
 * @property array<string, string|null>|null $description
 * @property bool $active
 * @property array<array-key, mixed>|null $settings
 * @property string $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read FinancerModule|DivisionModule|null $pivot
 * @property-read Collection<int, Division> $divisions
 * @property-read int|null $divisions_count
 * @property-read Collection<int, Financer> $financers
 * @property-read int|null $financers_count
 *
 * @method static ModuleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Module newModelQuery()
 * @method static Builder<static>|Module newQuery()
 * @method static Builder<static>|Module onlyTrashed()
 * @method static Builder<static>|Module query()
 * @method static Builder<static>|Module whereActive($value)
 * @method static Builder<static>|Module whereCategory($value)
 * @method static Builder<static>|Module whereCreatedAt($value)
 * @method static Builder<static>|Module whereDeletedAt($value)
 * @method static Builder<static>|Module whereDescription($value)
 * @method static Builder<static>|Module whereId($value)
 * @method static Builder<static>|Module whereName($value)
 * @method static Builder<static>|Module whereSettings($value)
 * @method static Builder<static>|Module whereUpdatedAt($value)
 * @method static Builder<static>|Module withTrashed()
 * @method static Builder<static>|Module withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Module extends LoggableModel implements Auditable
{
    use AuditableModel;
    use Cachable,HasFactory, HasUuids, SoftDeletes;
    use HasTranslations;

    protected static function logName(): string
    {
        return 'module';
    }

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public array $translatable = ['name', 'description'];

    protected $casts = [
        'id' => 'string',
        'name' => 'array',
        'description' => 'array',
        'settings' => 'array',
        'external_id' => 'array',
    ];

    /**
     * @return BelongsToMany<Division, Module>
     */
    public function divisions(): BelongsToMany
    {
        // @phpstan-ignore-next-line
        return $this->belongsToMany(
            Division::class,
            'division_module',
            'module_id',
            'division_id',
            'id',
            'id'
        )->using(DivisionModule::class)
            ->withPivot(['active'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @return BelongsToMany<Financer, Module>
     */
    public function financers(): BelongsToMany
    {
        // @phpstan-ignore-next-line
        return $this->belongsToMany(
            Financer::class,
            'financer_module',
            'module_id',
            'financer_id',
            'id',
            'id'
        )->using(FinancerModule::class)
            ->withPivot(['active', 'promoted'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @return HasMany<Integration, $this>
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class, 'module_id', 'id');
    }

    /** @phpstan-ignore-next-line */
    public function pinnedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_pinned_modules')->withTimestamps();
    }

    /**
     * @param  int|string  $divisionId
     * @param  array<string, mixed>  $pivot
     * @param  array<string, mixed>  $pivot
     */
    public function attachDivision($divisionId, array $pivot): void
    {
        $this->divisions()->attach($divisionId, $pivot);

        activity('module')
            ->performedOn($this)
            ->log("Division ID {$divisionId} attaché au module {$this->name}");
    }

    /**
     * @param  int|string  $divisionId
     */
    public function detachDivision($divisionId): void
    {
        $this->divisions()->detach($divisionId);

        activity('module')
            ->performedOn($this)
            ->log("Division ID {$divisionId} détaché du module {$this->name}");
    }

    /**
     * @param  int|string  $financerId
     * @param  array<string, mixed>  $pivot
     */
    public function attachFinancer($financerId, array $pivot): void
    {
        $this->financers()->attach($financerId, $pivot);

        activity('module')
            ->performedOn($this)
            ->log("Financer ID {$financerId} attaché au module {$this->name}");
    }

    /**
     * @param  int|string  $financerId
     */
    public function detachFinancer($financerId): void
    {
        $this->financers()->detach($financerId);

        activity('module')
            ->performedOn($this)
            ->log("Financer ID {$financerId} détaché du module {$this->name}");
    }
}
