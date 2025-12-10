<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\Models\Merchant;
use Illuminate\Database\Eloquent\Builder;

trait MerchantFiltersAndScopes
{
    /**
     * Scope a query to search merchants by name.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeSearchByName(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    /**
     * Scope a query to find a merchant by merchant_id.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeByMerchantId(Builder $query, string $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope a query to order merchants by name.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('name', $direction);
    }

    /**
     * Scope a query to filter merchants by country.
     * Includes merchants with country matching the parameter OR country = 'EUR'.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where(function (Builder $q) use ($country): void {
            $q->where('country', $country)
                ->orWhere('country', 'EUR');
        });
    }

    /**
     * Scope a query to filter merchants by category through the relationship.
     *
     * @param  Builder<Merchant>  $query
     * @return Builder<Merchant>
     */
    public function scopeWithCategory(Builder $query, int $categoryId): Builder
    {
        return $query->whereHas('categories', function ($q) use ($categoryId): void {
            $q->where('category_id', $categoryId);
        });
    }
}
