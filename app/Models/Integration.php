<?php

namespace App\Models;

use App\Traits\AuditableModel;
use App\Traits\Cachable;
use App\Traits\PipeFiltrable;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;

/**
 * @property string $id
 * @property string $module_id
 * @property string $name
 * @property string $type
 * @property string|null $description
 * @property bool $active
 * @property array<array-key, mixed>|null $settings
 * @property string|null $resources_count_query
 * @property string|null $resources_count_unit
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
 * @property-read Module|null $module
 *
 * @method static IntegrationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Integration newModelQuery()
 * @method static Builder<static>|Integration newQuery()
 * @method static Builder<static>|Integration onlyTrashed()
 * @method static Builder<static>|Integration pipeFiltered()
 * @method Builder<static>|Integration pipeFiltered()
 * @method static Builder<static>|Integration query()
 * @method static Builder<static>|Integration whereActive($value)
 * @method static Builder<static>|Integration whereCreatedAt($value)
 * @method static Builder<static>|Integration whereDeletedAt($value)
 * @method static Builder<static>|Integration whereDescription($value)
 * @method static Builder<static>|Integration whereId($value)
 * @method static Builder<static>|Integration whereModuleId($value)
 * @method static Builder<static>|Integration whereName($value)
 * @method static Builder<static>|Integration whereSettings($value)
 * @method static Builder<static>|Integration whereType($value)
 * @method static Builder<static>|Integration whereUpdatedAt($value)
 * @method static Builder<static>|Integration withTrashed()
 * @method static Builder<static>|Integration withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Integration extends LoggableModel implements Auditable
{
    use AuditableModel, Cachable, HasFactory, HasUuids, PipeFiltrable, SoftDeletes;

    protected $casts = [
        'external_id' => 'array',
        'id' => 'string',
        'settings' => 'array',
    ];

    protected static function logName(): string
    {
        return 'integration';
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'id');
    }

    /**
     * @param  int|string  $divisionId
     * @param  array<string, mixed>  $pivot
     */
    public function attachDivision($divisionId, array $pivot): void
    {
        $this->divisions()->attach($divisionId, $pivot);

        activity('integration')
            ->performedOn($this)
            ->log("Division ID {$divisionId} attachée à l'intégration {$this->name}");
    }

    /**
     * @return BelongsToMany<Division, $this, DivisionModule>
     */
    public function divisions(): BelongsToMany
    {
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
     * @param  int|string  $divisionId
     */
    public function detachDivision($divisionId): void
    {
        $this->divisions()->detach($divisionId);

        activity('integration')
            ->performedOn($this)
            ->log("Division ID {$divisionId} détachée de l'intégration {$this->name}");
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public function attachFinancer(string $financerId, array $pivot): void
    {
        $this->financers()->attach($financerId, $pivot !== [] ? $pivot : ['active' => true]);

        activity('integration')
            ->performedOn($this)
            ->log("Financer ID {$financerId} attaché à l'intégration {$this->name}");
    }

    /**
     * @return BelongsToMany<Financer, $this, FinancerModule>
     */
    public function financers(): BelongsToMany
    {
        return $this->belongsToMany(
            Financer::class,
            'financer_module',
            'module_id',
            'financer_id',
            'id',
            'id'
        )->using(FinancerModule::class)
            ->withPivot(['active'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @param  int|string  $financerId
     */
    public function detachFinancer($financerId): void
    {
        $this->financers()->detach($financerId);

        activity('integration')
            ->performedOn($this)
            ->log("Financer ID {$financerId} détaché de l'intégration {$this->name}");
    }
}
