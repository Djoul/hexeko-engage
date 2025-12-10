<?php

namespace App\Integrations\InternalCommunication\Models;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Observers\ArticleObserver;
use App\Integrations\InternalCommunication\Traits\ArticleAccessorsAndHelpers;
use App\Integrations\InternalCommunication\Traits\ArticleFiltersAndScopes;
use App\Integrations\InternalCommunication\Traits\ArticleRelations;
use App\Models\Concerns\MarksAsDemo;
use App\Models\Financer;
use App\Models\LoggableModelHasMedia;
use App\Models\Traits\HasFinancer;
use App\Models\User;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $id
 * @property string $financer_id
 * @property string $author_id
 * @property string $title
 * @property string $content
 * @property-read Collection<int, Tag> $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Financer $financer
 * @property-read User $author
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, ArticleInteraction> $interactions
 * @property-read int|null $interactions_count
 *
 * @method static ArticleFactory factory($count = null, $state = [])
 * @method static Builder<self> newModelQuery()
 * @method static Builder<self> newQuery()
 * @method static Builder<self> onlyTrashed()
 * @method static Builder<self> query()
 * @method static Builder<self> whereAuthorId($value)
 * @method static Builder<self> whereContent($value)
 * @method static Builder<self> whereCreatedAt($value)
 * @method static Builder<self> whereDeletedAt($value)
 * @method static Builder<self> whereFinancerId($value)
 * @method static Builder<self> whereId($value)
 * @method static Builder<self> wherePublishedAt($value)
 * @method static Builder<self> whereStatus($value)
 * @method static Builder<self> whereTags($value)
 * @method static Builder<self> whereTitle($value)
 * @method static Builder<self> whereUpdatedAt($value)
 * @method static Builder<self> withTrashed()
 * @method static Builder<self> withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[ObservedBy(ArticleObserver::class)]
class Article extends LoggableModelHasMedia implements Auditable
{
    use ArticleAccessorsAndHelpers, AuditableModel;
    use ArticleFiltersAndScopes;
    use ArticleRelations;
    use HasFactory;
    use HasFinancer;
    use HasUuids;
    use InteractsWithMedia;
    use MarksAsDemo;
    use SoftDeletes;

    protected $table = 'int_communication_rh_articles';

    protected $casts = [
        'id' => 'string',
        'financer_id' => 'string',
        'author_id' => 'string',
        'segment_id' => 'string',
        'content' => 'array',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }

    /**
     * Fields that can be used for modular sorting.
     *
     * @var string[]
     */
    public static array $sortable = [
        'translations.title',
        'translations.status',
        'translations.published_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Default sorting field.
     */
    public static string $defaultSortField = 'created_at';

    /**
     * Default sorting direction.
     */
    public static string $defaultSortDirection = 'desc';

    /**
     * Fields that can be used for global search.
     *
     * @var string[]
     */
    public static array $searchable = [
        'translations.title',
        'translations.content',
    ];

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('illustration');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // don't optimize AVIF images
        if ($media?->mime_type === 'image/avif') {
            return;
        }

        $this->addMediaConversion('illustration')
            ->performOnCollections('illustration')
            ->width(800)
            ->quality(85)
            ->optimize();

        $this->addMediaConversion('mobile')
            ->performOnCollections('illustration')
            ->width(400)
            ->quality(85)
            ->optimize();
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
     * Get field mapping for sortable aliases.
     * Maps user-friendly field names to actual sortable field names.
     *
     * @return array<string, string>
     */
    public static function getSortableFieldMap(): array
    {
        return [
            'title' => 'translations.title',
            'status' => 'translations.status',
            'published_at' => 'translations.published_at',
        ];
    }
}
