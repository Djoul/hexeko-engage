<?php

namespace App\Integrations\Survey\Models;

use App\Integrations\Survey\Database\factories\FavoriteFactory;
use App\Models\LoggableModel;
use App\Models\Traits\HasCreator;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $user_id
 * @property string $survey_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read User $user
 * @property-read Survey $survey
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
class Favorite extends LoggableModel
{
    use HasCreator;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'int_survey_favorites';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'survey_id' => 'string',
        ];
    }

    protected static function logName(): string
    {
        return 'favorite';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    protected static function newFactory(): FavoriteFactory
    {
        return FavoriteFactory::new();
    }
}
