<?php

namespace App\Integrations\Survey\Models;

use App\Integrations\Survey\Database\factories\SubmissionFactory;
use App\Models\Financer;
use App\Models\LoggableModel;
use App\Models\Traits\HasCreator;
use App\Models\Traits\HasFinancer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property string $survey_id
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer $financer
 * @property-read User $user
 * @property-read Survey $survey
 * @property-read Collection<int, Answer> $answers
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Submission extends LoggableModel
{
    use HasCreator;
    use HasFactory;
    use HasFinancer;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_submissions';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'financer_id' => 'string',
            'user_id' => 'string',
            'survey_id' => 'string',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function logName(): string
    {
        return 'submission';
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    #[Scope]
    public function whereUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    public function progressRateFor(User $user): float
    {
        $total = DB::table('int_survey_questionables')
            ->where('questionable_id', $this->survey_id)
            ->where('questionable_type', Survey::class)
            ->count();

        if ($total === 0) {
            return 0;
        }

        $answered = $this->answers()
            ->where('int_survey_answers.user_id', $user->id)
            ->count('int_survey_answers.question_id');

        return round(($answered / $total) * 100, 2);
    }

    public function answersCountFor(User $user): int
    {
        return $this->answers()
            ->where('int_survey_answers.user_id', $user->id)
            ->count('int_survey_answers.question_id');
    }

    protected static function newFactory(): SubmissionFactory
    {
        return SubmissionFactory::new();
    }
}
