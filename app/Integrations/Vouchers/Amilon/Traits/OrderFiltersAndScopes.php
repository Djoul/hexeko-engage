<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\Models\Order;
use Illuminate\Database\Eloquent\Builder;

trait OrderFiltersAndScopes
{
    /**
     * Scope a query to only include orders with a given status.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to find an order by external_order_id.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeByExternalOrderId(Builder $query, string $externalOrderId): Builder
    {
        return $query->where('external_order_id', $externalOrderId);
    }

    /**
     * Scope a query to find an order by order_id.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeByOrderId(Builder $query, string $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope a query to find orders by merchant_id.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeByMerchantId(Builder $query, string $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope a query to find orders by user_id.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeByUserId(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to order orders by created_at.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeOrderByCreatedAt(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('created_at', $direction);
    }

    /**
     * Scope a query to order orders by amount.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeOrderByAmount(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('amount', $direction);
    }
}
