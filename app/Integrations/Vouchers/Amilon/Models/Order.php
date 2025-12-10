<?php

namespace App\Integrations\Vouchers\Amilon\Models;

use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Traits\OrderAccessorsAndHelpers;
use App\Integrations\Vouchers\Amilon\Traits\OrderFiltersAndScopes;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $merchant_id
 * @property float $amount
 * @property string $external_order_id
 * @property string|null $order_id
 * @property string|null $status
 * @property float|null $price_paid
 * @property string|null $voucher_url
 * @property string|null $user_id
 * @property string|null $payment_id
 * @property string|null $product_id
 * @property float|null $total_amount
 * @property string|null $payment_method
 * @property string|null $stripe_payment_id
 * @property float|null $balance_amount_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $order_date
 * @property string|null $order_status
 * @property float|null $gross_amount
 * @property float|null $net_amount
 * @property int|null $total_requested_codes
 * @property array<string, mixed>|null $metadata
 * @property string|null $voucher_code
 * @property string|null $voucher_pin
 * @property string|null $product_name
 * @property string $currency
 * @property int $recovery_attempts
 * @property string|null $last_error
 * @property Carbon|null $last_recovery_attempt
 * @property Carbon|null $next_retry_at
 * @property string|null $order_recovered_id
 * @property-read User|null $user
 * @property-read Merchant|null $merchant
 * @property-read Product|null $product
 * @property-read Collection<int, OrderItem> $items
 * @property-read Order|null $recoveredOrder
 * @property-read Order|null $newOrder
 */
class Order extends Model
{
    use HasFactory;
    use HasUuids;
    use OrderAccessorsAndHelpers;
    use OrderFiltersAndScopes;

    /**
     * Get the factory for the model.
     */
    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_vouchers_amilon_orders';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'float',
        'price_paid' => 'float',
        'gross_amount' => 'float',
        'net_amount' => 'float',
        'total_amount' => 'float',
        'balance_amount_used' => 'float',
        'total_requested_codes' => 'integer',
        'recovery_attempts' => 'integer',
        'metadata' => 'array',
        'order_date' => 'datetime',
        'last_recovery_attempt' => 'datetime',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product that was ordered.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the merchant that the order is for.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'merchant_id');
    }

    /**
     * Get the items for this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the original cancelled order that was recovered.
     * This relationship is from the new order to the old cancelled order.
     */
    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_recovered_id');
    }

    /**
     * Get the new order that was created from this cancelled order.
     * This relationship is from the old cancelled order to the new order.
     */
    public function newOrder(): HasOne
    {
        return $this->hasOne(Order::class, 'order_recovered_id');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
