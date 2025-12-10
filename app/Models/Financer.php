<?php

namespace App\Models;

use App\Contracts\Searchable;
use App\Enums\FinancerStatus;
use App\Models\Concerns\MarksAsDemo;
use App\Models\Traits\Financer\FinancerAccessorsAndHelpers;
use App\Models\Traits\Financer\FinancerFiltersAndScopes;
use App\Models\Traits\Financer\FinancerRelations;
use App\Models\Traits\HasDivisionScopes;
use App\Observers\FinancerObserver;
use App\Traits\AuditableModel;
use Database\Factories\FinancerFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $name
 * @property array<array-key, mixed>|null $external_id
 * @property string $timezone
 * @property string|null $registration_number
 * @property string|null $registration_country
 * @property string|null $website
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $vat_number
 * @property string|null $representative_id
 * @property string $division_id
 * @property bool $active
 * @property string $status
 * @property string $company_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Division $division
 * @property-read FinancerUser|FinancerModule|FinancerIntegration|null $pivot
 * @property-read Collection<int, Integration> $integrations
 * @property-read int|null $integrations_count
 * @property-read Collection<int, Module> $modules
 * @property-read int|null $modules_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static FinancerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Financer newModelQuery()
 * @method static Builder<static>|Financer newQuery()
 * @method static Builder<static>|Financer onlyTrashed()
 * @method static Builder<static>|Financer pipeFiltered()
 * @method Builder<static>|Financer pipeFiltered()
 * @method static Builder<static>|Financer query()
 * @method static Builder<static>|Financer whereCreatedAt($value)
 * @method static Builder<static>|Financer whereDeletedAt($value)
 * @method static Builder<static>|Financer whereDivisionId($value)
 * @method static Builder<static>|Financer whereExternalId($value)
 * @method static Builder<static>|Financer whereIban($value)
 * @method static Builder<static>|Financer whereId($value)
 * @method static Builder<static>|Financer whereName($value)
 * @method static Builder<static>|Financer whereRegistrationCountry($value)
 * @method static Builder<static>|Financer whereRegistrationNumber($value)
 * @method static Builder<static>|Financer whereRepresentativeId($value)
 * @method static Builder<static>|Financer whereTimezone($value)
 * @method static Builder<static>|Financer whereUpdatedAt($value)
 * @method static Builder<static>|Financer whereVatNumber($value)
 * @method static Builder<static>|Financer whereWebsite($value)
 * @method static Builder<static>|Financer withTrashed()
 * @method static Builder<static>|Financer withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(FinancerObserver::class)]
class Financer extends LoggableModel implements Auditable, HasMedia, Searchable
{
    use AuditableModel;
    use FinancerAccessorsAndHelpers, FinancerFiltersAndScopes, FinancerRelations;
    use HasDivisionScopes;
    use HasFactory, HasUuids, SoftDeletes;
    use InteractsWithMedia;
    use MarksAsDemo;

    public const STATUS_ACTIVE = FinancerStatus::ACTIVE;

    public const STATUS_PENDING = FinancerStatus::PENDING;

    public const STATUS_ARCHIVED = FinancerStatus::ARCHIVED;

    protected static int $cacheTtl = 3600; // 60 minutes

    protected $casts = [
        'id' => 'string',
        'external_id' => 'array',
        'active' => 'boolean',
        'available_languages' => 'array',
        'status' => 'string',
    ];

    // Removed automatic eager loading to prevent N+1 queries
    // Load these relations explicitly when needed
    // protected $with = ['integrations', 'division'];

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile();
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
            'registration_number',
            'vat_number',
            'iban',
            'website',
        ];
    }

    /**
     * Get the relations and their fields that should be searchable.
     *
     * @return array<string, array<int, string>>
     */
    public function getSearchableRelations(): array
    {
        return [
            'division' => ['name'],
        ];
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
