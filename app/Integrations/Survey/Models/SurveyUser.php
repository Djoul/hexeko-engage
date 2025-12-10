<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class SurveyUser extends Pivot
{
    protected $table = 'int_survey_survey_user';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'survey_id' => 'string',
            'user_id' => 'string',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
