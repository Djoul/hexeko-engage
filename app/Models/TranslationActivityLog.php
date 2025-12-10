<?php

namespace App\Models;

use App\Attributes\GlobalScopedModel;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

#[GlobalScopedModel]
class TranslationActivityLog extends Model implements Auditable
{
    use AuditableModel, HasFactory;

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
