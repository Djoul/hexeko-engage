<?php

namespace App\Models;

use App\Models\Traits\HasDivisionThroughFinancer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Models\Audit as AuditModel;

class Audit extends AuditModel
{
    use HasDivisionThroughFinancer;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_type',
        'user_id',
        'financer_id',
        'event',
        'auditable_id',
        'auditable_type',
        'old_values',
        'new_values',
        'url',
        'ip_address',
        'user_agent',
        'tags',
    ];

    /**
     * Get the financer that owns the audit.
     */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }
}
