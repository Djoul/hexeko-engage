<?php

namespace App\Integrations\HRTools\Models;

use App\Integrations\HRTools\Database\factories\LinkFactory;
use App\Integrations\HRTools\Traits\LinkAccessorsAndHelpers;
use App\Integrations\HRTools\Traits\LinkFiltersAndScopes;
use App\Integrations\HRTools\Traits\LinkRelations;
use App\Models\Concerns\MarksAsDemo;
use App\Models\Financer;
use App\Models\LoggableModelHasMedia;
use App\Models\Traits\HasDivisionThroughFinancer;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $url
 * @property string|null $logo_url
 * @property string|null $api_endpoint
 * @property string|null $front_endpoint
 * @property string $financer_id
 * @property int $position
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Financer|null $financer
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 *
 * @method static Builder<static>|Link newModelQuery()
 * @method static Builder<static>|Link newQuery()
 * @method static Builder<static>|Link onlyTrashed()
 * @method static Builder<static>|Link query()
 * @method static Builder<static>|Link whereApiEndpoint($value)
 * @method static Builder<static>|Link whereCreatedAt($value)
 * @method static Builder<static>|Link whereDeletedAt($value)
 * @method static Builder<static>|Link whereDescription($value)
 * @method static Builder<static>|Link whereFinancerId($value)
 * @method static Builder<static>|Link whereFrontEndpoint($value)
 * @method static Builder<static>|Link whereId($value)
 * @method static Builder<static>|Link whereLogoUrl($value)
 * @method static Builder<static>|Link whereName($value)
 * @method static Builder<static>|Link wherePosition($value)
 * @method static Builder<static>|Link whereUpdatedAt($value)
 * @method static Builder<static>|Link whereUrl($value)
 * @method static Builder<static>|Link withTrashed()
 * @method static Builder<static>|Link withoutTrashed()
 * @method static Builder<static>|Link related()
 * @method static Builder<static>|Link pipeFiltered()
 *
 * @mixin \Eloquent
 */
class Link extends LoggableModelHasMedia implements Auditable
{
    use AuditableModel;
    use HasDivisionThroughFinancer;
    use HasFactory;
    use HasTranslations;
    use HasUuids;
    use InteractsWithMedia;
    use LinkAccessorsAndHelpers;
    use LinkFiltersAndScopes;
    use LinkRelations;
    use MarksAsDemo;
    use SoftDeletes;

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public array $translatable = [
        'name',
        'description',
        'url',
    ];

    /**
     * Fields that can be used for modular sorting.
     *
     * @var string[]
     */
    public static array $sortable = [
        'name',   // Now sortable with SortApplier's translatable field handling
        'position',
        'created_at',
        'updated_at',
    ];

    /**
     * Default sorting field.
     */
    public static string $defaultSortField = 'position';

    /**
     * Default sorting direction.
     */
    public static string $defaultSortDirection = 'asc';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_outils_rh_links';

    protected $casts = [
        'id' => 'string',
        'financer_id' => 'string',
        'position' => 'integer',
    ];

    protected $with = ['media'];

    public function registerMediaCollections(): void
    {
        $this
            ->addMediaCollection('logo')
            ->singleFile();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return LinkFactory::new();
    }
}
