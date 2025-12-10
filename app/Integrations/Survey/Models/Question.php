<?php

namespace App\Integrations\Survey\Models;

use App\Contracts\Searchable;
use App\Integrations\Survey\Database\factories\QuestionFactory;
use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Pipelines\FilterPipelines\QuestionPipeline;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property string|null $financer_id
 * @property int|null $original_question_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $archived_at
 * @property array<string, string> $text
 * @property array<string, string> $help_text
 * @property string $type
 * @property array<string, mixed> $metadata
 * @property int $id
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer|null $financer
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Question extends LoggableModel implements Searchable
{
    use HasArchivedAt;
    use HasCreator;
    use HasFactory;
    use HasFinancer;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_questions';

    /** @var array<string> */
    public array $translatable = ['text', 'help_text'];

    /** @var array<string> */
    public static array $sortable = [
        'text',
        'created_at',
        'updated_at',
    ];

    public static string $defaultSortField = 'text';

    public static string $defaultSortDirection = 'asc';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'financer_id' => 'string',
            'metadata' => 'array',
            'is_default' => 'boolean',
            'type' => QuestionTypeEnum::class,
        ];
    }

    public function getSearchableFields(): array
    {
        return [
            'text',
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
        return 'question';
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
    public function byTheme(Builder $query, string $themeId): Builder
    {
        return $query->where('theme_id', $themeId);
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
     * Apply the QuestionPipeline to the given query.
     *
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    #[Scope]
    public static function pipeFiltered(Builder $query): Builder
    {
        return resolve(QuestionPipeline::class)->apply($query);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    /**
     * Get the questionnaire that this question belongs to.
     * A question can only be linked to one questionnaire.
     * This method returns the first (and only) questionnaire from the polymorphic relationship.
     */
    public function questionnaire(): ?Questionnaire
    {
        return $this->questionnaires()->first();
    }

    /**
     * Get all questionnaires this question belongs to.
     * Note: A question should only be linked to one questionnaire.
     * Use questionnaire() to get the single questionnaire relationship.
     */
    public function questionnaires(): MorphToMany
    {
        return $this->morphedByMany(Questionnaire::class, 'questionable', 'int_survey_questionables')
            ->withPivot('position')->withTimestamps()->orderBy('position');
    }

    /**
     * Get the survey that this question belongs to.
     * A question can only be linked to one survey.
     * This method returns the first (and only) survey from the polymorphic relationship.
     */
    public function survey(): ?Survey
    {
        return $this->surveys()->first();
    }

    /**
     * Get all surveys this question belongs to.
     * Note: A question should only be linked to one survey.
     * Use survey() to get the single survey relationship.
     */
    public function surveys(): MorphToMany
    {
        return $this->morphedByMany(Survey::class, 'questionable', 'int_survey_questionables')
            ->withPivot('position')->withTimestamps()->orderBy('position');
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /**
     * Duplicate this question for the given financer.
     *
     * Creates a copy of the question with all its options, ensuring that
     * each survey/questionnaire can have independent modifications.
     */
    public function duplicate(?string $financerId): self
    {
        $this->load('options');

        $duplicatedQuestion = $this->replicate();
        $duplicatedQuestion->financer_id = $financerId;
        $duplicatedQuestion->parent_question_id = $this->id;
        $duplicatedQuestion->original_question_id = $this->original_question_id ?? $this->id;
        $duplicatedQuestion->deleted_at = null;
        $duplicatedQuestion->archived_at = null;
        $duplicatedQuestion->is_default = false;
        $duplicatedQuestion->save();

        // Duplicate question options if they exist
        /** @var QuestionOption $option */
        foreach ($this->options as $option) {
            /** @var QuestionOption $duplicatedOption */
            $duplicatedOption = $option->replicate();
            $duplicatedOption->question_id = (string) $duplicatedQuestion->id;
            $duplicatedOption->original_question_option_id = (string) $option->id;
            $duplicatedOption->save();
        }

        // Reload the options relationship for the duplicated question
        $duplicatedQuestion->load('options');

        return $duplicatedQuestion;
    }

    public function getAnswersMetrics()
    {
        $questionType = $this->type instanceof QuestionTypeEnum
            ? $this->type->value
            : $this->type;

        // Load options to map values to option titles
        $this->load('options');
        $optionsMap = $this->options->keyBy('id');

        $getOptionTitle = function (string $optionId) use ($optionsMap): ?string {
            $option = $optionsMap->get($optionId);

            return $option ? $option->text : null;
        };

        $totalAnswers = $this->answers->count();

        return match ($questionType) {
            QuestionTypeEnum::SCALE => $this->answers->groupBy('answer.value')->map(function ($group) use ($getOptionTitle, $totalAnswers): array {
                $value = $group->first()->answer['value'] ?? null;
                $title = is_string($value) ? $getOptionTitle($value) : null;

                return [
                    'value' => $title ?? $value,
                    'count' => $group->count(),
                    'percentage' => $totalAnswers > 0 ? round(($group->count() / $totalAnswers) * 100) : 0.0,
                ];
            }),
            QuestionTypeEnum::SINGLE_CHOICE => $this->answers->groupBy('answer.value')->map(function ($group) use ($getOptionTitle, $totalAnswers): array {
                $value = $group->first()->answer['value'] ?? null;
                $title = is_string($value) ? $getOptionTitle($value) : null;

                return [
                    'value' => $title ?? $value,
                    'count' => $group->count(),
                    'percentage' => $totalAnswers > 0 ? round(($group->count() / $totalAnswers) * 100) : 0.0,
                ];
            }),
            QuestionTypeEnum::MULTIPLE_CHOICE => $this->answers->flatMap(function ($answer) {
                return $answer->answer['value'] ?? [];
            })->groupBy(fn ($value) => $value)->map(function ($group, $key) use ($getOptionTitle, $totalAnswers): array {
                $title = is_string($key) ? $getOptionTitle($key) : null;

                return [
                    'value' => $title ?? $key,
                    'count' => $group->count(),
                    'percentage' => $totalAnswers > 0 ? round(($group->count() / $totalAnswers) * 100) : 0.0,
                ];
            })->values(),
            QuestionTypeEnum::TEXT => collect([]),
            default => collect([]),
        };
    }

    public function canBeModified(): bool
    {
        return ! ($this->survey()?->isClosed() || $this->survey()?->isArchived() || $this->survey()?->isActive());
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }
}
