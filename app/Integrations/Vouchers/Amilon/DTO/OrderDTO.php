<?php

namespace App\Integrations\Vouchers\Amilon\DTO;

use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @implements Arrayable<string, mixed>
 */
class OrderDTO implements Arrayable, Jsonable
{
    /**
     * Create a new OrderDTO instance.
     */
    public function __construct(
        public readonly string $merchant_id,
        public readonly float $amount,
        public readonly string $external_order_id,
        public readonly ?string $order_id = null,
        public readonly ?string $status = null,
        public readonly ?float $price_paid = null,
        public readonly ?string $voucher_url = null,
        public readonly ?string $created_at = null,
        public readonly ?string $payment_id = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $items = null,
        public readonly ?string $order_date = null,
        public readonly ?float $gross_amount = null,
        public readonly ?float $net_amount = null,
        public readonly ?int $total_requested_codes = null,
        public readonly ?string $order_status = null,
        public readonly ?string $voucher_code = null,
        public readonly ?string $voucher_pin = null,
        public readonly ?string $product_name = null,
        public readonly string $currency = 'EUR',
    ) {}

    /**
     * Create a new OrderDTO from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            merchant_id: array_key_exists('merchant_id', $data) ? self::ensureString($data['merchant_id']) : '',
            amount: array_key_exists('amount', $data) ? self::ensureFloat($data['amount']) : 0.0,
            external_order_id: array_key_exists('external_order_id', $data) ? self::ensureString($data['external_order_id']) : '',
            order_id: array_key_exists('order_id', $data) ? self::ensureStringOrNull($data['order_id']) : null,
            status: array_key_exists('status', $data) ? self::ensureStringOrNull($data['status']) : null,
            price_paid: array_key_exists('price_paid', $data) ? self::ensureFloatOrNull($data['price_paid']) : null,
            voucher_url: array_key_exists('voucher_url', $data) ? self::ensureStringOrNull($data['voucher_url']) : null,
            created_at: array_key_exists('created_at', $data) ? self::ensureStringOrNull($data['created_at']) : null,
            payment_id: array_key_exists('payment_id', $data) ? self::ensureStringOrNull($data['payment_id']) : null,
            items: array_key_exists('items', $data) && is_array($data['items']) ?
                   self::ensureArrayWithStringKeys($data['items']) : null,
            order_date: array_key_exists('order_date', $data) ? self::ensureStringOrNull($data['order_date']) : null,
            gross_amount: array_key_exists('gross_amount', $data) ? self::ensureFloatOrNull($data['gross_amount']) : null,
            net_amount: array_key_exists('net_amount', $data) ? self::ensureFloatOrNull($data['net_amount']) : null,
            total_requested_codes: array_key_exists('total_requested_codes', $data) ? self::ensureIntOrNull($data['total_requested_codes']) : null,
            order_status: array_key_exists('order_status', $data) ? self::ensureStringOrNull($data['order_status']) : null,
            voucher_code: array_key_exists('voucher_code', $data) ? self::ensureStringOrNull($data['voucher_code']) : null,
            voucher_pin: array_key_exists('voucher_pin', $data) ? self::ensureStringOrNull($data['voucher_pin']) : null,
            product_name: array_key_exists('product_name', $data) ? self::ensureStringOrNull($data['product_name']) : null,
            currency: array_key_exists('currency', $data) ? self::ensureString($data['currency']) : 'EUR',
        );
    }

    /**
     * Create a new OrderDTO from API response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromApiResponse(array $response, Product $product, float $amount, string $external_order_id, ?string $payment_id = null): self
    {
        // Process order items and vouchers
        $items = [];
        $firstVoucherCode = null;
        $firstVoucherPin = null;
        $firstVoucherUrl = null;
        $productName = null;
        $totalRequestedCodes = null;

        if (array_key_exists('orderRows', $response) && is_array($response['orderRows'])) {
            foreach ($response['orderRows'] as $row) {
                if (is_array($row)) {
                    /** @var array<string, mixed> $row */
                    $items[] = OrderItemDTO::fromApiResponse($row, $product)->toArray();
                }
            }
        }

        // Process vouchers if they exist at the order level (case-insensitive check)
        $vouchersKey = null;
        foreach (array_keys($response) as $key) {
            if (strtolower($key) === 'vouchers') {
                $vouchersKey = $key;
                break;
            }
        }

        if ($vouchersKey !== null && is_array($response[$vouchersKey])) {
            $vouchers = $response[$vouchersKey];
            $totalRequestedCodes = count($vouchers);

            // Extract first voucher data for order-level fields
            if ($vouchers !== [] && is_array($vouchers[0])) {
                $firstVoucher = $vouchers[0];
                // Handle case variations in API response keys
                $firstVoucherCode = self::ensureStringOrNull(
                    $firstVoucher['CardCode'] ??
                    $firstVoucher['cardCode'] ??
                    $firstVoucher['card_code'] ?? null
                );
                $firstVoucherPin = self::ensureStringOrNull(
                    $firstVoucher['Pin'] ??
                    $firstVoucher['pin'] ?? null
                );
                $firstVoucherUrl = self::ensureStringOrNull(
                    $firstVoucher['VoucherLink'] ??
                    $firstVoucher['voucherLink'] ??
                    $firstVoucher['voucher_link'] ?? null
                );
                $productName = self::ensureStringOrNull(
                    $firstVoucher['RetailerName'] ??
                    $firstVoucher['retailerName'] ??
                    $firstVoucher['retailer_name'] ?? null
                );
            }

            // If we have vouchers but no items, create items based on vouchers
            if ($items === []) {
                $vouchersByProduct = [];
                foreach ($vouchers as $voucher) {
                    if (! is_array($voucher)) {
                        continue;
                    }
                    $productId = self::ensureString(
                        $voucher['ProductId'] ??
                        $voucher['productId'] ??
                        $voucher['product_id'] ?? ''
                    );
                    if (! array_key_exists($productId, $vouchersByProduct)) {
                        $vouchersByProduct[$productId] = [
                            'productId' => $productId,
                            'quantity' => 0,
                            'vouchers' => [],
                        ];
                    }
                    $vouchersByProduct[$productId]['quantity']++;
                    $vouchersByProduct[$productId]['vouchers'][] = $voucher;
                }

                foreach ($vouchersByProduct as $productData) {
                    $items[] = OrderItemDTO::fromApiResponse($productData, $product)->toArray();
                }
            } else {
                // If we have both items and vouchers, assign vouchers to the appropriate items
                $vouchersByProduct = [];
                foreach ($vouchers as $voucher) {
                    if (! is_array($voucher)) {
                        continue;
                    }
                    $productId = self::ensureString(
                        $voucher['ProductId'] ??
                        $voucher['productId'] ??
                        $voucher['product_id'] ?? ''
                    );
                    if (! array_key_exists($productId, $vouchersByProduct)) {
                        $vouchersByProduct[$productId] = [];
                    }
                    $vouchersByProduct[$productId][] = $voucher;
                }

                foreach ($items as &$item) {
                    $productId = self::ensureString($item['product_id'] ?? '');
                    if (array_key_exists($productId, $vouchersByProduct)) {
                        $item['vouchers'] = $vouchersByProduct[$productId];
                    }
                }
            }
        }

        // Handle OrderDate case variations
        $orderDate = null;
        if (array_key_exists('OrderDate', $response)) {
            $orderDate = self::ensureStringOrNull($response['OrderDate']);
        } elseif (array_key_exists('orderDate', $response)) {
            $orderDate = self::ensureStringOrNull($response['orderDate']);
        } elseif (array_key_exists('order_date', $response)) {
            $orderDate = self::ensureStringOrNull($response['order_date']);
        }

        return new self(
            merchant_id: $product->merchant_id,
            amount: $amount * 100, // Euros to cents
            external_order_id: $external_order_id,
            order_id: array_key_exists('order_id', $response) ? self::ensureStringOrNull($response['order_id']) : null,
            status: array_key_exists('orderStatus', $response) ? self::ensureStringOrNull($response['orderStatus']) :
                   (array_key_exists('status', $response) ? self::ensureStringOrNull($response['status']) : null),
            price_paid: $product->net_price,
            voucher_url: $firstVoucherUrl ??
                         (array_key_exists('voucher_url', $response) ? self::ensureStringOrNull($response['voucher_url']) : null),
            created_at: date('Y-m-d H:i:s'),
            payment_id: $payment_id,
            items: $items !== [] ? self::ensureArrayWithStringKeys($items) : null,
            order_date: $orderDate,
            gross_amount: array_key_exists('grossAmount', $response) ? self::ensureFloatOrNull($response['grossAmount']) :
                         (array_key_exists('gross_amount', $response) ? self::ensureFloatOrNull($response['gross_amount']) : null),
            net_amount: array_key_exists('netAmount', $response) ? self::ensureFloatOrNull($response['netAmount']) :
                       (array_key_exists('net_amount', $response) ? self::ensureFloatOrNull($response['net_amount']) : null),
            total_requested_codes: $totalRequestedCodes ??
                                  (array_key_exists('totalRequestedCodes', $response) ? self::ensureIntOrNull($response['totalRequestedCodes']) :
                                  (array_key_exists('total_requested_codes', $response) ? self::ensureIntOrNull($response['total_requested_codes']) : null)),
            order_status: array_key_exists('orderStatus', $response) ? self::ensureStringOrNull($response['orderStatus']) :
                         (array_key_exists('order_status', $response) ? self::ensureStringOrNull($response['order_status']) : null),
            voucher_code: $firstVoucherCode,
            voucher_pin: $firstVoucherPin,
            product_name: $productName,
            currency: 'EUR', // Default to EUR, can be enhanced to extract from API if available
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
     * Ensure a value is a float.
     */
    private static function ensureFloat(mixed $value): float
    {
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
     * Ensure a value is a float or null.
     */
    private static function ensureFloatOrNull(mixed $value): ?float
    {
        if (is_null($value)) {
            return null;
        }

        return self::ensureFloat($value);
    }

    /**
     * Ensure a value is an int or null.
     */
    private static function ensureIntOrNull(mixed $value): ?int
    {
        if (is_null($value)) {
            return null;
        }

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
            'merchant_id' => $this->merchant_id,
            'amount' => $this->amount,
            'external_order_id' => $this->external_order_id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'price_paid' => $this->price_paid,
            'voucher_url' => $this->voucher_url,
            'created_at' => $this->created_at,
            'payment_id' => $this->payment_id,
            'items' => $this->items,
            'order_date' => $this->order_date,
            'gross_amount' => $this->gross_amount,
            'net_amount' => $this->net_amount,
            'total_requested_codes' => $this->total_requested_codes,
            'order_status' => $this->order_status,
            'voucher_code' => $this->voucher_code,
            'voucher_pin' => $this->voucher_pin,
            'product_name' => $this->product_name,
            'currency' => $this->currency,
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
