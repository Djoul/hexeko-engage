<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Searchable;
use App\Models\Concerns\MarksAsDemo;
use App\Models\Traits\Division\DivisionAccessorsAndHelpers;
use App\Models\Traits\Division\DivisionFiltersAndScopes;
use App\Models\Traits\Division\DivisionRelations;
use App\Traits\AuditableModel;
use Database\Factories\DivisionFactory;
use Eloquent;
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
 * @property string|null $description
 * @property string $country
 * @property string $currency
 * @property string $timezone
 * @property string $language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool|null $use_factory
 *
 * @method static DivisionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Division newModelQuery()
 * @method static Builder<static>|Division newQuery()
 * @method static Builder<static>|Division onlyTrashed()
 * @method static Builder<static>|Division pipeFiltered()
 * @method Builder<static>|Division pipeFiltered()
 * @method static Builder<static>|Division query()
 * @method static Builder<static>|Division whereCountry($value)
 * @method static Builder<static>|Division whereCreatedAt($value)
 * @method static Builder<static>|Division whereCurrency($value)
 * @method static Builder<static>|Division whereDeletedAt($value)
 * @method static Builder<static>|Division whereDescription($value)
 * @method static Builder<static>|Division whereId($value)
 * @method static Builder<static>|Division whereLanguage($value)
 * @method static Builder<static>|Division whereName($value)
 * @method static Builder<static>|Division whereTimezone($value)
 * @method static Builder<static>|Division whereUpdatedAt($value)
 * @method static Builder<static>|Division withTrashed()
 * @method static Builder<static>|Division withoutTrashed()
 *
 * @property string|null $remarks
 *
 * @method static Builder<static>|Division whereRemarks($value)
 *
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read DivisionModule|DivisionIntegration|null $pivot
 * @property-read Collection<int, Integration> $integrations
 * @property-read int|null $integrations_count
 * @property-read Collection<int, Module> $modules
 * @property-read int|null $modules_count
 *
 * @mixin Eloquent
 */
class Division extends LoggableModel implements Auditable, Searchable
{
    use AuditableModel;
    use DivisionAccessorsAndHelpers;
    use DivisionFiltersAndScopes;
    use DivisionRelations;

    /** @use HasFactory<DivisionFactory> */
    use HasFactory;

    use HasUuids;
    use MarksAsDemo;
    use SoftDeletes;

    /**
     * Fields that can be used for modular sorting.
     *
     * @var string[]
     */
    public static array $sortable = [
        'name',
        'country',
        'currency',
        'timezone',
        'language',
        'created_at',
        'updated_at',
    ];

    // 60 minutes
    /**
     * Default sorting field.
     */
    public static string $defaultSortField = 'created_at';

    /**
     * Default sorting direction.
     */
    public static string $defaultSortDirection = 'desc';

    protected static int $cacheTtl = 3600;

    protected static function logName(): string
    {
        return 'division';
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'status' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the fields that should be searchable for this model.
     *
     * @return array<int, string>
     */
    public function getSearchableFields(): array
    {
        return [
            'name',
            'remarks',
            'country',
        ];
    }

    /**
     * Get the relations and their fields that should be searchable.
     *
     * @return array<string, array<int, string>>
     */
    public function getSearchableRelations(): array
    {
        return [];
    }

    /**
     * Get the SQL expression for sorting a virtual field.
     * Returns null if the field should use standard sorting.
     */
    public static function getSortableExpression(string $field): ?string
    {
        return match ($field) {
            default => null,
        };
    }

    /**
     * Get the SQL expression for searching a virtual field.
     * Returns null if the field should use standard searching.
     */
    public static function getSearchableExpression(string $field): ?string
    {
        return match ($field) {
            default => null,
        };
    }
}
