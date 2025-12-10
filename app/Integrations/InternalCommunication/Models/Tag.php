<?php

namespace App\Integrations\InternalCommunication\Models;

use App\Integrations\InternalCommunication\Traits\TagAccessorsAndHelpers;
use App\Integrations\InternalCommunication\Traits\TagFiltersAndScopes;
use App\Integrations\InternalCommunication\Traits\TagRelations;
use App\Models\Financer;
use App\Models\LoggableModel;
use App\Models\Traits\HasFinancer;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $id
 * @property string $financer_id
 * @property array<string, string> $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Financer $financer
 * @property-read Collection<int, Article> $articles
 * @property-read int|null $articles_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 *
 * @method static Builder<self> forFinancer(string $financerId)
 * @method static Builder<self> searchLabel(string $search)
 * @method static Builder<self> usedInArticles()
 * @method static Builder<self> unused()
 * @method static Builder<self> newModelQuery()
 * @method static Builder<self> newQuery()
 * @method static Builder<self> query()
 * @method static Builder<self> whereId($value)
 * @method static Builder<self> whereFinancerId($value)
 * @method static Builder<self> whereLabel($value)
 * @method static Builder<self> whereCreatedAt($value)
 * @method static Builder<self> whereUpdatedAt($value)
 * @method static Builder<self> whereDeletedAt($value)
 * @method static Builder<self> onlyTrashed()
 * @method static Builder<self> withTrashed()
 * @method static Builder<self> withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Tag extends LoggableModel implements Auditable
{
    use AuditableModel;
    use HasFactory;
    use HasFinancer;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;
    use TagAccessorsAndHelpers;
    use TagFiltersAndScopes;
    use TagRelations;

    /**
     * Get the log name for activity logging.
     */
    protected static function logName(): string
    {
        return 'tag';
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_communication_rh_tags';

    /**
     * The attributes that are translatable.
     *
     * @var array<int, string>
     */
    public array $translatable = ['label'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'financer_id' => 'string',
    ];

    /**
     * Fields that can be used for modular sorting.
     *
     * @var string[]
     */
    public static array $sortable = [
        'label',
        'created_at',
        'updated_at',
    ];

    /**
     * Default sorting field.
     */
    public static string $defaultSortField = 'label';

    /**
     * Default sorting direction.
     */
    public static string $defaultSortDirection = 'asc';

    /**
     * Fields that can be used for global search.
     *
     * @var string[]
     */
    public static array $searchable = [
        'label',
    ];
}
