<?php

namespace App\Models;

use App\Models\Traits\HasDivisionThroughFinancer;
use App\Traits\AuditableModel;
use App\Traits\GlobalCachable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class LLMRequest extends Model implements Auditable
{
    use AuditableModel;
    use GlobalCachable;
    use HasDivisionThroughFinancer;
    use HasUuids;

    protected $casts = [
        'id' => 'string',
        'requestable_id' => 'string',
        'messages' => 'array',
    ];

    protected $table = 'llm_requests';

    /**
     * @return BelongsTo<Financer, self>
     */
    /** @phpstan-ignore-next-line */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }

    /**
     * @return MorphTo<Model, self>
     */
    /** @phpstan-ignore-next-line */
    public function requestable(): MorphTo
    {
        return $this->morphTo('requestable', 'requestable_type', 'requestable_id');
    }
}
