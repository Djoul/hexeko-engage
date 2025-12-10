<?php

namespace App\Integrations\Vouchers\Amilon\Models;

use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Traits\ProductAccessorsAndHelpers;
use App\Integrations\Vouchers\Amilon\Traits\ProductFiltersAndScopes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Product model for Amilon voucher products.
 *
 * IMPORTANT: All monetary amounts (price, net_price) are stored in CENTS.
 * 1 euro = 100 cents. This avoids floating point precision issues.
 * Discount is stored as percentage * 100 in DB (e.g., 6.67% = 667 in DB).
 *
 * @property string $id
 * @property string $name
 * @property string|null $product_code
 * @property string|null $category_id
 * @property string $merchant_id
 * @property int|null $price Price in cents (e.g., 1000 = €10.00)
 * @property int|null $net_price Net price in cents after discount
 * @property float|null $discount Discount as percentage (e.g., 6.67 for 6.67%)
 * @property string|null $currency
 * @property string|null $country
 * @property string|null $description
 * @property string|null $image_url
 * @property bool $is_available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Merchant|null $merchant
 * @property-read Category|null $category
 */
class Product extends Model
{
    use HasFactory;
    use HasUuids;
    use ProductAccessorsAndHelpers;
    use ProductFiltersAndScopes;

    /**
     * Get the factory for the model.
     */
    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_vouchers_amilon_products';

    /**
     * The attributes that should be cast.
     *
     * Note: price and net_price are stored as integers (cents).
     * Discount is handled by accessors/mutators (stored as percentage * 100).
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'price' => 'integer',        // Price in cents
        'net_price' => 'integer',    // Net price in cents
        // discount is handled by accessors/mutators
        'is_available' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the merchant that the product belongs to.
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id', 'merchant_id');
    }

    /**
     * Get the category that the product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
