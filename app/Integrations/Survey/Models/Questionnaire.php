<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Models;

use App\Contracts\Searchable;
use App\Integrations\Survey\Database\factories\QuestionnaireFactory;
use App\Integrations\Survey\Enums\QuestionnaireStatusEnum;
use App\Integrations\Survey\Enums\QuestionnaireTypeEnum;
use App\Integrations\Survey\Pipelines\FilterPipelines\QuestionnairePipeline;
use App\Models\Financer;
use App\Models\LoggableModel;
use App\Models\Traits\HasArchivedAt;
use App\Models\Traits\HasCreator;
use App\Models\Traits\HasFinancer;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property string|null $financer_id
 * @property string $type
 * @property array<string, mixed> $settings
 * @property bool $is_default
 * @property Carbon|null $deleted_at
 * @property Carbon|null $archived_at
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property array<string, string> $instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer|null $financer
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Questionnaire extends LoggableModel implements Searchable
{
    use HasArchivedAt;
    use HasCreator;
    use HasFactory;
    use HasFinancer;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_questionnaires';

    /** @var array<string> */
    public array $translatable = ['name', 'description', 'instructions'];

    /** @var array<string> */
    public static array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Questionnaire $questionnaire): void {
            if (! $questionnaire->status) {
                $questionnaire->status = QuestionnaireStatusEnum::PUBLISHED;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'settings' => 'array',
            'is_default' => 'boolean',
            'type' => QuestionnaireTypeEnum::class,
            'status' => 'string',
        ];
    }

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

    protected static function logName(): string
    {
        return 'questionnaire';
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
    public function byType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Apply the QuestionnairePipeline to the given query.
     *
     * @param  Builder<Questionnaire>  $query
     * @return Builder<Questionnaire>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(QuestionnairePipeline::class)->apply($query);
    }

    public function questions(): MorphToMany
    {
        return $this->morphToMany(Question::class, 'questionable', 'int_survey_questionables')
            ->withPivot('position')->withTimestamps()->orderBy('position');
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    protected static function newFactory(): QuestionnaireFactory
    {
        return QuestionnaireFactory::new();
    }
}
