<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait StripePaymentRelations
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
