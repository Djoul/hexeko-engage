<?php

namespace App\Integrations\Vouchers\Amilon\DTO;

use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @implements Arrayable<string, mixed>
 */
class OrderItemDTO implements Arrayable, Jsonable
{
    /**
     * Create a new OrderItemDTO instance.
     */
    public function __construct(
        public readonly string $product_id,
        public readonly int $quantity,
        public readonly ?float $price = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $vouchers = null,
    ) {}

    /**
     * Create a new OrderItemDTO from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            product_id: array_key_exists('product_id', $data) ? self::ensureString($data['product_id']) : '',
            quantity: array_key_exists('quantity', $data) ? self::ensureInt($data['quantity']) : 0,
            price: array_key_exists('price', $data) ? self::ensureFloatOrNull($data['price']) : null,
            vouchers: array_key_exists('vouchers', $data) && is_array($data['vouchers']) ?
                self::ensureArrayWithStringKeys($data['vouchers']) : null,
        );
    }

    /**
     * Create a new OrderItemDTO from API response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromApiResponse(array $response, Product $product): self
    {
        // Always use the product's UUID for database relationships
        $productId = (string) $product->id;

        return new self(
            product_id: $productId,
            quantity: array_key_exists('quantity', $response) ? self::ensureInt($response['quantity']) : 0,
            price: array_key_exists('price', $response) ? self::ensureFloatOrNull($response['price']) : null,
            vouchers: array_key_exists('vouchers', $response) && is_array($response['vouchers']) ?
                self::ensureArrayWithStringKeys($response['vouchers']) : null,
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
     * Ensure a value is an int.
     */
    private static function ensureInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
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
     * Ensure array has string keys and mixed values.
     *
     * @param  array<mixed>  $array
     * @return array<string, mixed>
     */
    private static function ensureArrayWithStringKeys(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $stringKey = is_string($key) ? $key : (string) $key;
            $result[$stringKey] = $value;
        }

        return $result;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'vouchers' => $this->vouchers,
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
