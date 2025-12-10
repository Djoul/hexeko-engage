<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Models;

use App\Integrations\Payments\Stripe\Database\factories\StripePaymentFactory;
use App\Integrations\Payments\Stripe\Models\Traits\StripePaymentAccessorsAndHelpers;
use App\Integrations\Payments\Stripe\Models\Traits\StripePaymentRelations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StripePayment extends Model
{
    use HasFactory;
    use HasUuids;
    use StripePaymentAccessorsAndHelpers;
    use StripePaymentRelations;

    protected $table = 'int_stripe_payments';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'credit_amount' => 'integer',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'error_message' => 'string',
    ];

    protected static function newFactory(): StripePaymentFactory
    {
        return StripePaymentFactory::new();
    }
}
