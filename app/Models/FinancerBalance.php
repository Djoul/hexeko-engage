<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasDivisionThroughFinancer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancerBalance extends Model
{
    use HasDivisionThroughFinancer;
    use HasUuids;

    protected $table = 'financer_balances';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'last_invoice_at' => 'datetime',
            'last_payment_at' => 'datetime',
            'last_credit_at' => 'datetime',
        ];
    }

    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class);
    }
}
