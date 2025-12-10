<?php

namespace App\Models;

use App\Models\Traits\HasDivisionThroughFinancer;
use App\Traits\AuditableModel;
use App\Traits\Cachable;
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
 *
 * @property string $integration_id
 *
 * @method static Builder<static>|FinancerIntegration whereIntegrationId($value)
 *
 * @mixin \Eloquent
 */
class FinancerIntegration extends Pivot implements Auditable
{
    use AuditableModel;
    use Cachable;
    use HasDivisionThroughFinancer;
    use HasUuids;

    protected $table = 'financer_integration';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'financer_id',
        'integration_id',
    ];

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class, 'financer_id');
    }
}
