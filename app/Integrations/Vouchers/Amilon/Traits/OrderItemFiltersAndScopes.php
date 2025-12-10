<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;

trait OrderItemFiltersAndScopes
{
    /**
     * Scope a query to only include order items for a specific order.
     *
     * @param  Builder<OrderItem>  $query
     * @return Builder<OrderItem>
     */
    public function scopeForOrder(Builder $query, string $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope a query to only include order items for a specific product.
     *
     * @param  Builder<OrderItem>  $query
     * @return Builder<OrderItem>
     */
    public function scopeForProduct(Builder $query, string $productId): Builder
    {
        return $query->where('product_id', $productId);
    }
}
