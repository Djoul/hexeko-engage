<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Models;

use App\Models\Financer;
use App\Models\Traits\HasFinancer;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ManagerUser extends Pivot
{
    use HasFinancer;

    protected $table = 'manager_user';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'manager_id' => 'string',
            'user_id' => 'string',
            'financer_id' => 'string',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }
}
