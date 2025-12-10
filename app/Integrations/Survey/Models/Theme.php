<?php

namespace App\Integrations\Survey\Models;

use App\Contracts\Searchable;
use App\Integrations\Survey\Database\factories\ThemeFactory;
use App\Integrations\Survey\Pipelines\FilterPipelines\ThemePipeline;
use App\Models\Financer;
use App\Models\LoggableModel;
use App\Models\Traits\HasCreator;
use App\Models\Traits\HasNullableFinancer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property string|null $financer_id
 * @property bool $is_default
 * @property int $position
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer|null $financer
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Theme extends LoggableModel implements Searchable
{
    use HasCreator;
    use HasFactory;
    use HasNullableFinancer;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_themes';

    /** @var array<string> */
    public array $translatable = ['name', 'description'];

    /** @var array<string> */
    public static array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    public static string $defaultSortField = 'name';

    public static string $defaultSortDirection = 'asc';

    public function getSearchableFields(): array
    {
        return [
            'name',
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
            'financer_id' => 'string',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function logName(): string
    {
        return 'theme';
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function default(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function ordered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('name->'.app()->getLocale());
    }

    /**
     * Apply the ThemePipeline to the given query.
     *
     * @param  Builder<Theme>  $query
     * @return Builder<Theme>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(ThemePipeline::class)->apply($query);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function defaultQuestions(): HasMany
    {
        return $this->questions()->default();
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    public function isSystem(): bool
    {
        return $this->financer_id === null;
    }

    protected static function newFactory(): ThemeFactory
    {
        return ThemeFactory::new();
    }
}
