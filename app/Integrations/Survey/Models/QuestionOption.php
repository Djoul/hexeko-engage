<?php

namespace App\Integrations\Survey\Models;

use App\Integrations\Survey\Database\factories\QuestionOptionFactory;
use App\Models\LoggableModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property string $question_id
 * @property string|null $original_question_option_id
 * @property int $position
 * @property Carbon|null $deleted_at
 * @property array<string, string> $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class QuestionOption extends LoggableModel
{
    use HasFactory;
    use HasTranslations;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_question_options';

    /** @var array<string> */
    public array $translatable = ['text'];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'question_id' => 'string',
            'original_question_option_id' => 'string',
            'text' => 'array',
            'position' => 'integer',
        ];
    }

    protected static function logName(): string
    {
        return 'question_option';
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    protected static function newFactory(): QuestionOptionFactory
    {
        return QuestionOptionFactory::new();
    }
}
