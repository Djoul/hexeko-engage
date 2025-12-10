<?php

namespace App\Integrations\Vouchers\Amilon\Models;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Traits\MerchantAccessorsAndHelpers;
use App\Integrations\Vouchers\Amilon\Traits\MerchantFiltersAndScopes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $country
 * @property string $merchant_id
 * @property string|null $description
 * @property string|null $image_url
 * @property array<int, float>|null $available_amounts
 * @property float|null $average_discount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Merchant extends Model
{
    use HasFactory;
    use HasUuids;
    use MerchantAccessorsAndHelpers;
    use MerchantFiltersAndScopes;

    /**
     * Get the factory for the model.
     */
    protected static function newFactory(): MerchantFactory
    {
        return MerchantFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'int_vouchers_amilon_merchants';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'available_amounts' => 'array',
        'average_discount' => 'float',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'country',
        'merchant_id',
        'description',
        'image_url',
        'available_amounts',
        'average_discount',
    ];

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'merchant_id';
    }

    /**
     * The categories that belong to the merchant.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'int_vouchers_amilon_merchant_category',
            'merchant_id',
            'category_id'
        )->withTimestamps();
    }

    /**
     * Get the products for the merchant.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'merchant_id', 'merchant_id');
    }
}
