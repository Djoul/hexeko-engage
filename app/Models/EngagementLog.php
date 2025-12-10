<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class EngagementLog extends Model implements Auditable
{
    use AuditableModel, HasFactory, HasUuids;

    protected $table = 'engagement_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'metadata' => 'array',
        'logged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
