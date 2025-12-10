<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class EngagementMetric extends Model implements Auditable
{
    use AuditableModel;
    use HasUuids;

    protected $table = 'engagement_metrics';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'date' => 'date',
        'date_from' => 'date',
        'date_to' => 'date',
        'data' => 'array',
    ];
}
