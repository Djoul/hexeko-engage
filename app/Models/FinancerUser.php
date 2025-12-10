<?php

namespace App\Models;

use App\Enums\Languages;
use App\Models\Traits\HasDivisionThroughFinancer;
use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property bool $active
 * @property array|null $roles
 * @property string|null $sirh_id
 * @property string|null $language
 * @property Carbon $from
 * @property Carbon|null $to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|FinancerUser newModelQuery()
 * @method static Builder<static>|FinancerUser newQuery()
 * @method static Builder<static>|FinancerUser query()
 * @method static Builder<static>|FinancerUser whereActive($value)
 * @method static Builder<static>|FinancerUser whereCreatedAt($value)
 * @method static Builder<static>|FinancerUser whereFinancerId($value)
 * @method static Builder<static>|FinancerUser whereId($value)
 * @method static Builder<static>|FinancerUser whereUpdatedAt($value)
 * @method static Builder<static>|FinancerUser whereUserId($value)
 * @method static Builder<static>|FinancerUser whereSirhId($value)
 * @method static Builder<static>|FinancerUser whereFrom($value)
 * @method static Builder<static>|FinancerUser whereTo($value)
 *
 * @mixin \Eloquent
 */
class FinancerUser extends Pivot implements Auditable
{
    use AuditableModel;
    use HasDivisionThroughFinancer;
    use HasUuids;

    protected $table = 'financer_user';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'active' => 'boolean',
        'roles' => 'array',
        'from' => 'datetime',
        'to' => 'datetime',
        'language' => 'string',
        'started_at' => 'datetime',
    ];

    /**
     * The model's attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'from' => null, // Will be set to current timestamp by the database,
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (FinancerUser $model): void {
            // Ensure 'from' is set to current timestamp if not explicitly provided
            if ($model->from === null) {
                $model->from = now();
            }
        });
    }

    /**
     * Get the user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the financer
     */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class, 'financer_id');
    }

    /**
     * Get the work mode
     */
    public function workMode(): BelongsTo
    {
        return $this->belongsTo(WorkMode::class, 'work_mode_id');
    }

    /**
     * Get the job title
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    /**
     * Get the job level
     */
    public function jobLevel(): BelongsTo
    {
        return $this->belongsTo(JobLevel::class, 'job_level_id');
    }

    /**
     * Get the language value if it's a valid enum value, null otherwise
     */
    public function getLanguageEnum(): ?string
    {
        if ($this->language === null) {
            return null;
        }

        return Languages::hasValue($this->language) ? $this->language : null;
    }
}
