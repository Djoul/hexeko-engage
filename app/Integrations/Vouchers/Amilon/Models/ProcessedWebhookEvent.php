<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedWebhookEvent extends Model
{
    protected $table = 'int_amilon_processed_webhook_events';

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
