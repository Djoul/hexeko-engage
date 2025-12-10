<?php

namespace App\Integrations\Vouchers\Amilon\Models;

use App\Integrations\Vouchers\Amilon\Database\factories\OrderItemFactory;
use App\Integrations\Vouchers\Amilon\Traits\OrderItemAccessorsAndHelpers;
use App\Integrations\Vouchers\Amilon\Traits\OrderItemFiltersAndScopes;
use App\Integrations\Vouchers\Amilon\Traits\OrderItemRelations;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $order_id
 * @property string $product_id
 * @property int $quantity
 * @property float|null $price
 * @property array<string, mixed>|null $vouchers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Product $product
 */
class OrderItem extends Model
{
    use HasFactory;
    use HasUuids;
    use OrderItemAccessorsAndHelpers;
    use OrderItemFiltersAndScopes;
    use OrderItemRelations;

    /**
     * Get the factory for the model.
     */
    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_vouchers_amilon_order_items';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'price' => 'float',
        'vouchers' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order that the item belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the product that the item represents.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
