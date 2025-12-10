<?php

namespace App\Integrations\Vouchers\Amilon\Traits;

use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Database\Eloquent\Builder;

trait ProductFiltersAndScopes
{
    /**
     * Scope a query to only include products of a given category.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to search products by name.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeSearchByName(Builder $query, string $search): Builder
    {
        return $query->where('name', 'like', "%{$search}%");
    }

    /**
     * Scope a query to find products by merchant_id.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeByMerchantId(Builder $query, string $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId);
    }

    /**
     * Scope a query to find a product by id.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeById(Builder $query, string $id): Builder
    {
        return $query->where('id', $id);
    }

    /**
     * Scope a query to filter products by price range.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopePriceRange(Builder $query, float $min, float $max): Builder
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope a query to filter products by country.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    /**
     * Scope a query to order products by name.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOrderByName(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('name', $direction);
    }

    /**
     * Scope a query to order products by price.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOrderByPrice(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('price', $direction);
    }

    /**
     * Scope a query to order products by category.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeOrderByCategory(Builder $query, string $direction = 'asc'): Builder
    {
        return $query->orderBy('category', $direction);
    }
}
