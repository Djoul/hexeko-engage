<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Models;

use App\Contracts\Searchable;
use App\Integrations\Survey\Database\factories\SurveyFactory;
use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Models\Financer;
use App\Models\LoggableModel;
use App\Models\Segment;
use App\Models\Traits\HasArchivedAt;
use App\Models\Traits\HasCreator;
use App\Models\Traits\HasFinancer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Spatie\Translatable\HasTranslations;

/**
 * @property string|null $financer_id
 * @property string $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property array<string, string> $title
 * @property array<string, string> $description
 * @property array<string, string> $welcome_message
 * @property array<string, string> $thank_you_message
 * @property array<string, mixed> $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $archived_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read float $response_rate
 * @property-read float $completion_rate
 */
class Survey extends LoggableModel implements Searchable
{
    use HasArchivedAt;
    use HasCreator;
    use HasFactory;
    use HasFinancer;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_surveys';

    /** @var array<string> */
    public array $translatable = [
        'title',
        'description',
        'welcome_message',
        'thank_you_message',
    ];

    /** @var array<string> */
    public static array $sortable = [
        'title',
        'created_at',
        'updated_at',
    ];

    public static string $defaultSortField = 'created_at';

    public static string $defaultSortDirection = 'desc';

    public function getSearchableFields(): array
    {
        return [
            'title',
            'description',
        ];
    }

    public function getSearchableRelations(): array
    {
        return [];
    }

    public static function getSearchableExpression(string $field): ?string
    {
        return match ($field) {
            default => null,
        };
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'status' => 'string',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function logName(): string
    {
        return 'survey';
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function new(Builder $query): Builder
    {
        return $this->active($query)
            ->where($query->qualifyColumn('updated_at'), '>=', now()->subDays(3)->startOfDay());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function draft(Builder $query): Builder
    {
        return $query->where('status', SurveyStatusEnum::DRAFT);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function scheduled(Builder $query): Builder
    {
        return $query->where('status', SurveyStatusEnum::PUBLISHED)
            ->where('starts_at', '>', now());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('status', SurveyStatusEnum::PUBLISHED)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function closed(Builder $query): Builder
    {
        return $query->where('status', SurveyStatusEnum::PUBLISHED)
            ->where('ends_at', '<', now());
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function forFinancer(Builder $query, string $financerId): Builder
    {
        return $query->where('financer_id', $financerId);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function withinPeriod(Builder $query, Carbon|string $startDate, Carbon|string $endDate): Builder
    {
        return $query->whereBetween('starts_at', [$startDate, $endDate]);
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function questions(): MorphToMany
    {
        return $this->morphToMany(Question::class, 'questionable', 'int_survey_questionables')
            ->withPivot('position')->withTimestamps()->orderBy('position');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function submissionsFor(User $user): HasMany
    {
        return $this->submissions()->where('user_id', $user->id);
    }

    public function answers(): HasManyThrough
    {
        return $this->hasManyThrough(Answer::class, Submission::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'int_survey_survey_user', 'survey_id', 'user_id')
            ->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === SurveyStatusEnum::PUBLISHED &&
               $this->starts_at <= now() &&
               $this->ends_at >= now();
    }

    public function isScheduled(): bool
    {
        return $this->status === SurveyStatusEnum::PUBLISHED && $this->starts_at && $this->starts_at > now();
    }

    public function isDraft(): bool
    {
        return $this->status === SurveyStatusEnum::DRAFT;
    }

    public function isClosed(): bool
    {
        return $this->status === SurveyStatusEnum::PUBLISHED && $this->ends_at && $this->ends_at < now();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isNew(): bool
    {
        return $this->new(Survey::query())->where('id', $this->id)->exists();
    }

    public function canBeModified(): bool
    {
        return ! $this->isClosed() && ! $this->isArchived();
    }

    public function getDaysRemaining(): ?int
    {
        if (! $this->isActive()) {
            return null;
        }

        return (int) Carbon::now()->diffInDays($this->ends_at, false);
    }

    /**
     * Measure how many people responded (at least partially) compared to those who were invited.
     */
    protected function responseRate(): Attribute
    {
        return Attribute::make(
            get: fn (): float => once(function (): float {
                $usersCount = $this->users_count;
                $submissionsCount = $this->submissions()
                    ->distinct('user_id')
                    ->count('user_id');

                return $usersCount > 0
                    ? round(($submissionsCount / $usersCount * 100))
                    : 0.0;
            })
        );
    }

    /**
     * Measure how many people completed the survey compared to those who were invited.
     */
    protected function completionRate(): Attribute
    {
        return Attribute::make(
            get: fn (): float => once(function (): float {
                $completedSubmissionsCount = $this->submissions()
                    ->distinct('user_id')
                    ->whereNotNull('completed_at')
                    ->count();

                $usersCount = $this->users_count;

                return $usersCount > 0
                    ? round(($completedSubmissionsCount / $usersCount * 100))
                    : 0.0;
            })
        );
    }

    protected function startsAt(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value
                ? Date::parse($value)->setTimezone('UTC')
                : null
        );
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => $value
                ? Date::parse($value)->setTimezone('UTC')
                : null
        );
    }

    public function getStatus(): string
    {
        return match (true) {
            $this->isArchived() => SurveyStatusEnum::ARCHIVED,
            $this->isDraft() => SurveyStatusEnum::DRAFT,
            $this->isNew() => SurveyStatusEnum::NEW,
            $this->isScheduled() => SurveyStatusEnum::SCHEDULED,
            $this->isActive() => SurveyStatusEnum::ACTIVE,
            $this->isClosed() => SurveyStatusEnum::CLOSED,
            default => SurveyStatusEnum::DRAFT,
        };
    }

    public function progressRateFor(User $user): float
    {
        return $this->submissionsFor($user)->latest()->first()?->progressRateFor($user) ?? 0;
    }

    public function answersCountFor(User $user): int
    {
        return $this->submissionsFor($user)->latest()->first()?->answersCountFor($user) ?? 0;
    }

    public function isOngoingFor(User $user): bool
    {
        return $this->submissionsFor($user)->latest()->whereNull('completed_at')->exists() ?? false;
    }

    public function isCompletedFor(User $user): bool
    {
        return $this->submissionsFor($user)->latest()->whereNotNull('completed_at')->exists() ?? false;
    }

    public function isFavoriteFor(User $user): bool
    {
        return $this->favorites()->where('user_id', $user->id)->exists();
    }

    protected static function newFactory(): SurveyFactory
    {
        return SurveyFactory::new();
    }
}
