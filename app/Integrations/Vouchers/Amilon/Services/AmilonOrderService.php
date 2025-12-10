<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Events\Vouchers\VoucherPurchaseError;
use App\Events\Vouchers\VoucherPurchaseNotification;
use App\Integrations\Vouchers\Amilon\DTO\OrderDTO;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Enums\VoucherNotificationStatus;
use App\Integrations\Vouchers\Amilon\Events\Metrics\OrderCreated;
use App\Integrations\Vouchers\Amilon\Exceptions\AmilonOrderErrorException;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\OrderItem;
use App\Integrations\Vouchers\Amilon\Models\Product;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class AmilonOrderService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    /**
     * The base URL for the Amilon API.
     */
    protected string $baseUrl;

    protected string $contractId;

    /**
     * The default country key for this financer.
     */
    protected string $culture = 'pt-PT';

    /**
     * The cache TTL in seconds (24 hours).
     */
    protected int $cacheTtl = 86400;

    protected AmilonAuthService $authService;

    /**
     * Create a new AmilonService instance.
     */
    public function __construct(AmilonAuthService $authService)
    {
        $this->authService = $authService;
        $apiUrl = config('services.amilon.api_url');
        $contractId = config('services.amilon.contrat_id');
        $this->baseUrl = (is_string($apiUrl) ? $apiUrl : '').'/b2bwebapi/v1';
        $this->contractId = is_string($contractId) ? $contractId : '';
    }

    /**
     * Create an order for a voucher.
     *
     * @param  int  $quantity  The quantity of the product
     * @param  string  $externalOrderId  External order ID
     * @param  string  $userId  User ID to associate with the order
     * @param  string|null  $paymentId  Optional payment ID to associate with the order
     * @return array<string, mixed> The order data including vouchers
     *
     * @throws Exception If the order creation fails
     */
    public function createOrder(
        Product $product,
        int $quantity,
        string $externalOrderId,
        string $userId,
        ?string $paymentId = null
    ): array {
        // External order ID is always provided

        try {
            // Get access token for authentication
            $token = $this->authService->getAccessToken();

            // Prepare the payload
            $productCode = $product->product_code;
            if ($productCode === null) {
                throw new Exception('Product code is required');
            }
            $payload = $this->getPayload($productCode, $quantity, $externalOrderId);

            // Make API request to create order
            $url = "{$this->baseUrl}/Orders/create/".$this->contractId;
            $response = Http::withToken($token)
                ->post($url, $payload);

            // Log automatique de l'appel API
            $responseData = $response->json();
            $this->logApiCall(
                'POST',
                "/Orders/create/{$this->contractId}",
                $response->status(),
                is_array($responseData) ? $responseData : []
            );

            // Check if the request was successful
            if ($response->successful()) {
                $orderData = $response->json();
                // Create OrderDTO from API response
                $orderDataArray = is_array($orderData) ? $orderData : [];
                $amount = (float) ($product->net_price ?? 0);
                $orderDTO = OrderDTO::fromApiResponse($orderDataArray, $product, $amount, $externalOrderId, $paymentId);
                // Store order in database
                $order = Order::updateOrCreateFromDTO($orderDTO);

                // Associate with user if provided
                if ($userId !== '') {
                    $order->user_id = $userId;
                    $order->save();
                }

                // Dispatch event for metrics tracking
                event(new OrderCreated($userId, $order));

                // Broadcast WebSocket notification if user is known
                if ($userId !== '') {
                    broadcast(new VoucherPurchaseNotification(
                        userId: $userId,
                        orderId: (string) $order->id,
                        status: VoucherNotificationStatus::AMILON_ORDER_CREATED->value,
                        orderData: [
                            'external_order_id' => $order->external_order_id,
                            'amilon_order_id' => $order->order_id,
                            'product_name' => $product->name,
                            'quantity' => $quantity,
                            'status' => $order->status,
                        ],
                        message: 'Your voucher order has been submitted to Amilon'
                    ));
                }

                return $orderDTO->toArray();
            }

            // If authentication failed, try to refresh the token and retry
            if ($response->status() === 401) {
                Log::warning('Authentication failed, refreshing token and retrying');

                // Refresh token and retry
                $token = $this->authService->refreshToken();

                // Use the same URL as the original request
                $response = Http::withToken($token)
                    ->post($url, $payload);

                // Log automatique du retry
                $responseData = $response->json();
                $logData = is_array($responseData) ? $responseData : [];
                $this->logApiCall(
                    'POST',
                    "/Orders/create/{$this->contractId} (retry)",
                    $response->status(),
                    $logData
                );

                if ($response->successful()) {
                    $orderData = $response->json();

                    // Create OrderDTO from API response
                    $orderDataArray = is_array($orderData) ? $orderData : [];
                    $amount = (float) ($product->net_price ?? 0);
                    $orderDTO = OrderDTO::fromApiResponse($orderDataArray, $product, $amount, $externalOrderId, $paymentId);

                    // Store order in database
                    $order = Order::updateOrCreateFromDTO($orderDTO);

                    // Associate with user if provided
                    if ($userId !== '') {
                        $order->user_id = $userId;
                        $order->save();
                    }

                    // Dispatch event for metrics tracking
                    event(new OrderCreated($userId, $order));

                    // Broadcast WebSocket notification if user is known
                    if ($userId !== '') {
                        broadcast(new VoucherPurchaseNotification(
                            userId: $userId,
                            orderId: (string) $order->id,
                            status: VoucherNotificationStatus::AMILON_ORDER_CREATED->value,
                            orderData: [
                                'external_order_id' => $order->external_order_id,
                                'amilon_order_id' => $order->order_id,
                                'product_name' => $product->name,
                                'quantity' => $quantity,
                                'status' => $order->status,
                            ],
                            message: 'Your voucher order has been submitted to Amilon'
                        ));
                    }

                    return $orderDTO->toArray();
                }
            }

            // If API request failed, log the error and throw exception
            Log::error('Failed to create order', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            throw new AmilonOrderErrorException('Failed to create Amilon order: '.$response->body());
        } catch (Exception $e) {
            // Log any exceptions and rethrow
            Log::error('Exception while creating order', [
                'exception' => $e->getMessage(),
                'product_id' => $product->product_code,
                'quantity' => $quantity,
                'external_order_id' => $externalOrderId,
                'payment_id' => $paymentId,
            ]);

            // Broadcast error notification if user is known
            if ($userId !== '') {
                broadcast(new VoucherPurchaseError(
                    userId: $userId,
                    errorCode: 'AMILON_ORDER_FAILED',
                    errorMessage: 'Failed to create order with Amilon: '.$e->getMessage(),
                    context: [
                        'product_code' => $product->product_code,
                        'external_order_id' => $externalOrderId,
                    ]
                ));
            }

            throw $e;
        }
    }

    /**
     * Generate a unique external order ID.
     */
    public function generateExternalOrderId(): string
    {
        $year = date('Y');
        $uniqueId = Uuid::uuid4()->toString();

        return "ENGAGE-{$year}-{$uniqueId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPayload(string $productId, int $quantity, string $externalOrderId): array
    {
        return [
            'externalOrderId' => $externalOrderId,
            'orderRows' => [
                [
                    'productId' => $productId,
                    'quantity' => $quantity,
                ],
            ],
        ];
    }

    /**
     * Get information about an existing order from Amilon API.
     *
     * @param  string  $externalOrderId  The external order ID
     * @return array<string, mixed> The order data with updated status
     *
     * @throws Exception If the order info retrieval fails
     */
    public function getOrderInfo(string $externalOrderId): array
    {
        try {
            // Get access token for authentication
            $token = $this->authService->getAccessToken();

            // Make API request to get order info
            $url = "{$this->baseUrl}/Orders/{$externalOrderId}/complete";
            $response = Http::withToken($token)
                ->get($url);

            // Log automatique de l'appel API
            $responseJson = $response->json();
            $this->logApiCall(
                'GET',
                "/Orders/{$externalOrderId}/complete",
                $response->status(),
                is_array($responseJson) ? $responseJson : []
            );

            // Check if the request was successful
            if ($response->successful()) {
                $orderData = $response->json();
                // Find the order in the database
                $order = Order::byExternalOrderId($externalOrderId)->first();

                if (! $order) {
                    throw new AmilonOrderErrorException("Order not found with external ID: {$externalOrderId}");
                }

                // Map the status from the API response
                $orderDataArray = is_array($orderData) ? $orderData : [];
                $orderStatus = $orderDataArray['orderStatus'] ?? $orderDataArray['status'] ?? null;
                $status = $this->mapOrderStatus(is_string($orderStatus) ? $orderStatus : null);

                // Update the order in the database with the latest status
                $order->status = $status;
                $order->order_status = is_string($orderStatus) ? $orderStatus : null;
                $order->save();

                // Update order items with voucher data from API response
                $this->updateOrderItemsWithVouchers($order, $orderDataArray);

                // Reload the order with its relationships to ensure we have the latest data
                $order->load('items');

                // Return the updated order data
                return $order->toDTO()->toArray();
            }

            // If authentication failed, try to refresh the token and retry
            if ($response->status() === 401) {
                Log::warning('Authentication failed, refreshing token and retrying');

                // Refresh token and retry
                $token = $this->authService->refreshToken();

                $response = Http::withToken($token)
                    ->get($url);

                // Log automatique du retry
                $retryResponseJson = $response->json();
                $this->logApiCall(
                    'GET',
                    "/Orders/{$externalOrderId}/complete (retry)",
                    $response->status(),
                    is_array($retryResponseJson) ? $retryResponseJson : []
                );

                if ($response->successful()) {
                    $orderData = $response->json();

                    // Find the order in the database
                    $order = Order::byExternalOrderId($externalOrderId)->first();

                    if (! $order) {
                        throw new AmilonOrderErrorException("Order not found with external ID: {$externalOrderId}");
                    }

                    // Map the status from the API response
                    $orderDataArray = is_array($orderData) ? $orderData : [];
                    $orderStatus = $orderDataArray['orderStatus'] ?? $orderDataArray['status'] ?? null;
                    $status = $this->mapOrderStatus(is_string($orderStatus) ? $orderStatus : null);

                    // Update the order in the database with the latest status
                    $order->status = $status;
                    $order->order_status = is_string($orderStatus) ? $orderStatus : null;
                    $order->save();

                    // Update order items with voucher data from API response
                    $this->updateOrderItemsWithVouchers($order, $orderDataArray);

                    // Reload the order with its relationships to ensure we have the latest data
                    $order->load('items');

                    // Return the updated order data
                    return $order->toDTO()->toArray();
                }
            }

            // If API request failed, log the error and throw exception
            Log::error('Failed to get order info', [
                'status' => $response->status(),
                'body' => $response->body(),
                'external_order_id' => $externalOrderId,
            ]);

            throw new Exception('Failed to get order info: '.$response->body());
        } catch (Exception $e) {
            // Log any exceptions and rethrow
            Log::error('Exception while getting order info', [
                'exception' => $e->getMessage(),
                'external_order_id' => $externalOrderId,
            ]);

            throw $e;
        }
    }

    /**
     * Map the order status from Amilon API to our status values.
     *
     * @param  string|null  $status  The status from Amilon API
     * @return string The mapped status
     */
    private function mapOrderStatus(?string $status): string
    {
        return OrderStatus::fromAmilonStatus($status);
    }

    /**
     * Create an order from webhook data.
     *
     * @param  array<string, mixed>  $webhookData  The webhook data
     * @return array<string, mixed> The order data
     *
     * @throws Exception If the order creation fails
     */
    public function createFromWebhookData(array $webhookData): array
    {
        // Extract data from webhook
        $userId = $webhookData['user_id'] ?? null;
        $productId = $webhookData['product_id'] ?? null;
        $voucherAmount = $webhookData['voucher_amount'] ?? 0;
        $paymentIntentId = $webhookData['payment_intent_id'] ?? null;

        if (! is_string($productId) && ! is_int($productId)) {
            throw new Exception('Product ID is required in webhook data');
        }

        // Find the product
        $product = Product::find($productId);
        if (! $product) {
            throw new Exception('Product not found: '.$productId);
        }

        // Ensure voucherAmount is numeric
        if (! is_numeric($voucherAmount)) {
            throw new Exception('Invalid voucher amount');
        }

        // Create the order
        return $this->createOrder(
            $product,
            (int) $voucherAmount,
            null, // External order ID will be generated
            is_string($userId) ? $userId : null,
            is_string($paymentIntentId) ? $paymentIntentId : null
        );
    }

    /**
     * Get order status from Amilon API.
     *
     * @param  string  $amilonOrderId  The Amilon order ID
     * @return array<string, mixed> The order status data
     *
     * @throws Exception If the status retrieval fails
     */
    public function getOrderStatus(string $amilonOrderId): array
    {
        // Find the order by Amilon ID
        $order = Order::where('order_id', $amilonOrderId)->first();

        if (! $order) {
            throw new Exception("Order not found with Amilon ID: {$amilonOrderId}");
        }

        // Get the latest order info from API
        $orderInfo = $this->getOrderInfo($order->external_order_id);

        // Extract voucher information from items
        $voucherCode = null;
        $voucherUrl = null;

        if (array_key_exists('items', $orderInfo) && is_array($orderInfo['items'])) {
            foreach ($orderInfo['items'] as $item) {
                if (is_array($item) && array_key_exists('vouchers', $item) && is_array($item['vouchers']) && ! empty($item['vouchers'])) {
                    $firstVoucher = $item['vouchers'][0];
                    if (is_array($firstVoucher)) {
                        $voucherCode = $firstVoucher['code'] ?? null;
                        $voucherUrl = $firstVoucher['url'] ?? null;
                    }
                    break;
                }
            }
        }

        return [
            'status' => $orderInfo['status'] ?? 'unknown',
            'voucher_code' => $voucherCode,
            'voucher_url' => $voucherUrl,
            'expires_at' => null, // Not available in current structure
        ];
    }

    /**
     * Update order items with voucher data from API response.
     * This method is optimized to avoid N+1 queries by fetching all products at once.
     *
     * @param  Order  $order  The order to update
     * @param  array<string, mixed>  $orderDataArray  The order data from API
     */
    private function updateOrderItemsWithVouchers(Order $order, array $orderDataArray): void
    {
        if (! array_key_exists('orderRows', $orderDataArray) || ! is_array($orderDataArray['orderRows'])) {
            return;
        }

        // Collect all product codes first
        $productCodes = [];
        $vouchersByProductCode = [];

        foreach ($orderDataArray['orderRows'] as $row) {
            if (is_array($row) && array_key_exists('vouchers', $row) && is_array($row['vouchers'])) {
                $productCode = $row['productId'] ?? null;
                if ($productCode !== null && is_string($productCode)) {
                    $productCodes[] = $productCode;
                    $vouchersByProductCode[$productCode] = $row['vouchers'];
                }
            }
        }

        if ($productCodes === []) {
            return;
        }

        // Fetch all products in one query
        $products = Product::whereIn('product_code', $productCodes)
            ->get()
            ->keyBy('product_code');

        // Load order items to avoid additional queries
        $order->load('items');

        // Update order items with vouchers
        $updatedVouchers = [];
        foreach ($vouchersByProductCode as $productCode => $vouchers) {
            if ($products->has($productCode)) {
                $product = $products->get($productCode);

                if ($product !== null) {
                    /** @var OrderItem|null $orderItem */
                    $orderItem = $order->items->where('product_id', $product->id)->first();

                    if ($orderItem) {
                        $orderItem->setAttribute('vouchers', $vouchers);
                        $orderItem->save();

                        // Collect voucher info for notification
                        $updatedVouchers[] = [
                            'product_name' => $product->name,
                            'product_code' => $productCode,
                            'voucher_count' => count($vouchers),
                        ];
                    }
                }
            }
        }

        // Broadcast notification if vouchers were updated and user is known
        if ($updatedVouchers !== [] && $order->user_id) {
            broadcast(new VoucherPurchaseNotification(
                userId: $order->user_id,
                orderId: (string) $order->id,
                status: VoucherNotificationStatus::VOUCHERS_RECEIVED->value,
                orderData: [
                    'external_order_id' => $order->external_order_id,
                    'amilon_order_id' => $order->order_id,
                    'vouchers' => $updatedVouchers,
                ],
                message: 'Your vouchers have been received and are ready to use'
            ));
        }
    }

    /**
     * Get the provider name for logging.
     */
    public function getProviderName(): string
    {
        return 'amilon';
    }

    /**
     * Get the API version.
     */
    public function getApiVersion(): string
    {
        return 'v1';
    }

    /**
     * Check if the service is healthy.
     */
    public function isHealthy(): bool
    {
        try {
            $token = $this->authService->getAccessToken();

            return ! empty($token);
        } catch (Exception $e) {
            return false;
        }
    }
}
