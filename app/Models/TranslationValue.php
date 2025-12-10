<?php

namespace App\Models;

use App\Attributes\GlobalScopedModel;
use App\Traits\AuditableModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $translation_key_id
 * @property string $locale
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TranslationKey $key
 */
#[GlobalScopedModel]
class TranslationValue extends Model implements Auditable
{
    use AuditableModel, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'translation_key_id',
        'locale',
        'value',
    ];

    public function key(): BelongsTo
    {
        return $this->belongsTo(TranslationKey::class, 'translation_key_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(TranslationActivityLog::class, 'target_id')->where('target_type', 'value');
    }
}
