<?php

namespace App\Integrations\Survey\Models;

use App\Integrations\Survey\Database\factories\AnswerFactory;
use App\Models\LoggableModel;
use App\Models\Traits\HasCreator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $user_id
 * @property string $submission_id
 * @property string $question_id
 * @property array<string, mixed> $answer
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read User $user
 * @property-read Submission $submission
 * @property-read Question $question
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read string $financer_id
 */
class Answer extends LoggableModel
{
    use HasCreator;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_answers';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'submission_id' => 'string',
            'question_id' => 'string',
            'answer' => 'array',
        ];
    }

    protected static function logName(): string
    {
        return 'answer';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get the financer_id from the related submission
     */
    protected function financerId(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->submission->financer_id ?? false;
            },
        );
    }

    protected static function newFactory(): AnswerFactory
    {
        return AnswerFactory::new();
    }
}
