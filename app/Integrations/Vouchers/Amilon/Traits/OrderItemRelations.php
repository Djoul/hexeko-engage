<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait OrderItemRelations
{
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
