<?php

namespace App\Integrations\Vouchers\Amilon\DTO;

use App\Helpers\MoneyHelper;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @implements Arrayable<string, mixed>
 */
class ProductDTO implements Arrayable, Jsonable
{
    public string $id;

    /**
     * Create a new ProductDTO instance.
     *
     * NOTE: Monetary values (price, netPrice) are stored in CENTS.
     * Discount is stored as a PERCENTAGE (e.g., 20.5 for 20.5% discount).
     */
    public function __construct(
        public readonly string $name,
        public readonly string $productCode,
        public readonly ?string $category_id,
        public readonly string $merchant_id,
        public readonly ?int $price = null,        // Price in cents
        public readonly ?int $netPrice = null,     // Net price in cents
        public readonly ?float $discount = null,   // Discount as percentage
        public readonly ?string $currency = null,
        public readonly ?string $country = null,
        public readonly ?string $description = null,
        public readonly ?string $image_url = null,
    ) {}

    /**
     * Create a new ProductDTO from an array.
     *
     * Amilon API returns prices in EUROS, we convert them to CENTS for storage.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Get prices from API (in euros)
        $priceInEuros = array_key_exists('Price', $data) ? self::ensureFloatOrNull($data['Price']) : null;
        $netPriceInEuros = array_key_exists('NetPrice', $data) ? self::ensureFloatOrNull($data['NetPrice']) : null;

        // Convert euros to cents for storage
        $priceInCents = $priceInEuros !== null ? MoneyHelper::eurosToCents($priceInEuros) : null;
        $netPriceInCents = $netPriceInEuros !== null ? MoneyHelper::eurosToCents($netPriceInEuros) : null;

        // Calculate discount as percentage
        $discountInPercents = null;
        if ($priceInEuros !== null && $netPriceInEuros !== null) {
            if ($priceInEuros > 0) {
                // Calculate percentage: ((original - discounted) / original) * 100
                $discountInPercents = round((($priceInEuros - $netPriceInEuros) / $priceInEuros) * 100, 2);
            } else {
                // If original price is 0, discount is 0%
                $discountInPercents = 0.0;
            }
        }

        return new self(
            name: array_key_exists('Name', $data) ? self::ensureString($data['Name']) : '',
            productCode: array_key_exists('ProductCode', $data) ? self::ensureString($data['ProductCode']) : '',
            category_id: array_key_exists('MerchantCategory1', $data) ? self::ensureStringOrNull($data['MerchantCategory1']) : null,
            merchant_id: array_key_exists('merchant_id', $data) ? self::ensureString($data['merchant_id']) : (array_key_exists('MerchantId', $data) ? self::ensureString($data['MerchantId']) : (array_key_exists('MerchantCode', $data) ? self::ensureString($data['MerchantCode']) : '')),
            price: $priceInCents,
            netPrice: $netPriceInCents,
            discount: $discountInPercents,
            currency: array_key_exists('Currency', $data) ? self::ensureString($data['Currency']) : null,
            country: array_key_exists('MerchantCountry', $data) ? self::ensureString($data['MerchantCountry']) : null,
            description: array_key_exists('LongDescription', $data) ? self::ensureStringOrNull($data['LongDescription']) : null,
            image_url: array_key_exists('ImageUrl', $data) ? self::ensureStringOrNull($data['ImageUrl']) : null,
        );
    }

    /**
     * Ensure a value is a string.
     */
    private static function ensureString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Ensure a value is a string or null.
     */
    private static function ensureStringOrNull(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return self::ensureString($value);
    }

    /**
     * Ensure a value is a float or null.
     */
    private static function ensureFloatOrNull(mixed $value): ?float
    {
        if (is_null($value)) {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * Create a collection of ProductDTO from an array of products.
     *
     * @param  array<array<string, mixed>>  $products
     * @return array<ProductDTO>
     */
    public static function collection(array $products): array
    {
        return array_map(fn (array $product): ProductDTO => self::fromArray($product), $products);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'product_code' => $this->productCode,
            'category_id' => $this->category_id,
            'merchant_id' => $this->merchant_id,
            'price' => $this->price,
            'netPrice' => $this->netPrice,
            'discount' => $this->discount,
            'currency' => $this->currency,
            'country' => $this->country,
            'description' => $this->description,
            'image_url' => $this->image_url,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }
}
