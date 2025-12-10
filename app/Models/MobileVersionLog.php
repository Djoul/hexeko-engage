<?php

namespace App\Models;

use App\Models\Traits\HasDivisionThroughFinancer;
use Database\Factories\MobileVersionLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileVersionLog extends Model
{
    use HasDivisionThroughFinancer;
    use HasFactory;
    use HasUuids;

    protected $table = 'mobile_version_logs';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'financer_id' => 'string',
            'platform' => 'string',
            'version' => 'string',
            'minimum_required_version' => 'string',
            'should_update' => 'boolean',
            'update_type' => 'string',
            'ip_address' => 'string',
            'user_agent' => 'string',
            'metadata' => 'array',
        ];
    }

    protected static function logName(): string
    {
        return 'mobile_version_log';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    protected static function newFactory(): MobileVersionLogFactory
    {
        return new MobileVersionLogFactory;
    }
}
