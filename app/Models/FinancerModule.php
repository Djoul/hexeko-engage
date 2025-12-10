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
 * @property string $module_id
 * @property bool $active
 * @property bool $promoted
 * @property int|null $price_per_beneficiary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static>|FinancerModule newModelQuery()
 * @method static Builder<static>|FinancerModule newQuery()
 * @method static Builder<static>|FinancerModule query()
 * @method static Builder<static>|FinancerModule whereActive($value)
 * @method static Builder<static>|FinancerModule whereCreatedAt($value)
 * @method static Builder<static>|FinancerModule whereFinancerId($value)
 * @method static Builder<static>|FinancerModule whereId($value)
 * @method static Builder<static>|FinancerModule whereModuleId($value)
 * @method static Builder<static>|FinancerModule wherePricePerBeneficiary($value)
 * @method static Builder<static>|FinancerModule wherePromoted($value)
 * @method static Builder<static>|FinancerModule whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class FinancerModule extends Pivot implements Auditable
{
    use AuditableModel;
    use Cachable;
    use HasDivisionThroughFinancer;
    use HasUuids;

    protected $table = 'financer_module';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $casts = [
        'active' => 'bool',
        'promoted' => 'bool',
        'price_per_beneficiary' => 'int',
    ];

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class, 'financer_id');
    }
}
