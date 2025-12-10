<?php

namespace App\Models;

use App\Attributes\GlobalScopedModel;
use App\Traits\AuditableModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property string $key
 * @property string|null $group
 * @property string $interface_origin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, TranslationValue> $values
 */
#[GlobalScopedModel]
class TranslationKey extends Model implements Auditable
{
    use AuditableModel,  HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'group',
        'interface_origin',
    ];

    /**
     * Cache time to live in seconds.
     *
     * @var int
     */
    protected static $cacheTtl = 3600; // 1 hour

    public function values(): HasMany
    {
        return $this->hasMany(TranslationValue::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TranslationActivityLog::class, 'target_id')->where('target_type', 'key');
    }

    /**
     * Scope a query to only include translation keys for a specific interface.
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeForInterface($query, string $interface)
    {
        return $query->where('interface_origin', $interface);
    }
}
