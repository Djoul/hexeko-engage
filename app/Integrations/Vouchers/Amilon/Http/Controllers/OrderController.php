<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Actions\Vouchers\Amilon\RecoverFailedVoucherAction;
use App\Actions\Vouchers\PurchaseVoucherAction;
use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\PaymentMethod;
use App\Exceptions\DeprecatedFeatureException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidPaymentMethodException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Integrations\Vouchers\Amilon\OrderResource;
use App\Integrations\Vouchers\Amilon\Http\Requests\CreateOrderRequest;
use App\Integrations\Vouchers\Amilon\Http\Requests\PurchaseVoucherRequest;
use App\Integrations\Vouchers\Amilon\Http\Resources\OrderCollection;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Integrations\Vouchers\Amilon\Services\VoucherRecoveryService;
use App\Models\CreditBalance;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

#[Group('Modules/Vouchers/Amilon/Orders')]
class OrderController extends Controller
{
    public function __construct(
        protected AmilonOrderService $amilonService,
        private readonly VoucherRecoveryService $recoveryService,
        private readonly PurchaseVoucherAction $purchaseAction,
    ) {}

    /**
     * Get the order history for the authenticated user.
     *
     * Retrieves a paginated list of orders for the authenticated user.
     *
     * @response OrderCollection
     * @response 401 scenario="Unauthorized" {
     *   "error": "Unauthorized",
     *   "message": "You must be logged in to view your order history"
     * }
     * @response 500 scenario="Server error" {
     *   "error": "Failed to fetch order history",
     *   "message": "An error occurred while fetching your order history"
     * }
     */
    #[RequiresPermission(PermissionDefaults::VIEW_VOUCHER_ORDERS)]
    #[QueryParameter('status', description: 'Filter orders by status', type: 'string', example: 'completed')]
    #[QueryParameter('page', description: 'Page number for pagination', type: 'integer', example: '1')]
    #[QueryParameter('per_page', description: 'Number of items per page', type: 'integer', example: '20')]
    public function index(Request $request): OrderCollection|JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'You must be logged in to view your order history',
                ], 401);
            }

            $query = Order::query();

            $query->where('user_id', $user->id);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // TODO: payment_status column was removed - review filtering logic
            // if ($request->has('payment_status')) {
            //     $query->where('payment_status', $request->input('payment_status'));
            // }

            $orders = $query->with(['items', 'merchant.categories', 'product'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return new OrderCollection($orders);
        } catch (Exception $e) {
            Log::error('Error fetching order history', [
                'exception' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch order history',
                'message' => 'An error occurred while fetching your order history',
            ], 500);
        }
    }

    /**
     * Create a new order for a voucher (legacy endpoint).
     *
     * Creates a new voucher order for the authenticated user.
     *
     * @deprecated Use purchase() instead
     *
     * @response 201 scenario="Success" {
     *   "message": "Order created successfully",
     *   "data": {
     *     "id": "123e4567-e89b-12d3-a456-426614174000",
     *     "status": "pending",
     *     "total_amount": 5000
     *   }
     * }
     * @response 500 scenario="Server error" {
     *   "error": "Failed to create order",
     *   "message": "An error occurred while creating the order"
     * }
     *
     * @deprecated
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function store(CreateOrderRequest $request): JsonResponse
    {

        throw new DeprecatedFeatureException(
            'POST /api/v1/vouchers/amilon/store endpoint',
            'POST /api/v1/vouchers/amilon/purchase endpoint with appropriate payment method',
            '2025-08-06'
        );
    }

    /**
     * Get a specific order by ID or external_order_id.
     *
     * Retrieves details of a specific order.
     * The order must belong to the authenticated user unless admin.
     *
     * @response 200 scenario="Success" {
     *   "data": {
     *     "id": "123e4567-e89b-12d3-a456-426614174000",
     *     "external_order_id": "AMI-2024-001",
     *     "status": "completed",
     *     "total_amount": 5000,
     *     "recovery_info": {
     *       "attempts": 0,
     *       "can_retry": false,
     *       "last_error": null,
     *       "last_attempt_at": null,
     *     }
     *   }
     * }
     * @response 404 scenario="Not found" {
     *   "error": "Not found",
     *   "message": "Order not found"
     * }
     * @response 403 scenario="Forbidden" {
     *   "error": "Forbidden",
     *   "message": "You do not have permission to view this order"
     * }
     */
    #[RequiresPermission(PermissionDefaults::VIEW_VOUCHER_ORDERS)]
    public function show(string $orderIdentifier): JsonResponse
    {
        try {
            $user = Auth::user();

            // Try to find by ID first, then by external_order_id
            $order = Order::find($orderIdentifier)
                ?? Order::byExternalOrderId($orderIdentifier)->first();

            if (! $order) {
                return response()->json([
                    'error' => 'Not found',
                    'message' => 'Order not found',
                ], 404);
            }

            // Check permissions
            if ($user && $order->user_id !== $user->id) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to view this order',
                ], 403);
            }

            try {
                // Get the latest order info from Amilon API if external_order_id exists
                if ($order->external_order_id) {
                    $this->amilonService->getOrderInfo($order->external_order_id);
                    $order->refresh();
                }
            } catch (Exception $e) {
                Log::warning('Error getting order info from Amilon API, using database data', [
                    'exception' => $e->getMessage(),
                    'order_id' => $orderIdentifier,
                ]);
            }

            $resource = new OrderResource($order);
            $data = $resource->toArray(request());

            // Add recovery info
            $data['recovery_info'] = [
                'attempts' => $order->recovery_attempts,
                'can_retry' => $this->recoveryService->canRetry($order),
                'last_error' => $order->last_error,
                'last_attempt_at' => $order->last_recovery_attempt?->toIso8601String(),
            ];

            return response()->json([
                'data' => $data,
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching order', [
                'exception' => $e->getMessage(),
                'order_identifier' => $orderIdentifier,
            ]);

            return response()->json([
                'error' => 'Failed to fetch order',
                'message' => 'An error occurred while fetching the order',
            ], 500);
        }
    }

    /**
     * Get available payment options for the current user
     *
     * @deprecated since 2025-08-06 This endpoint is deprecated and will be removed in a future version
     *
     * @throws DeprecatedFeatureException
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function paymentOptions(Request $request): JsonResponse
    {
        throw new DeprecatedFeatureException(
            'GET /api/v1/vouchers/amilon/payment-options endpoint',
            'POST /api/v1/vouchers/amilon/purchase endpoint with appropriate payment method',
            '2025-08-06'
        );
    }

    /**
     * Purchase a voucher using balance, stripe, or mixed payment
     *
     * NOTE: All monetary amounts in request/response are in CENTS.
     * Example: balance_amount: 5000 = €50.00
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function purchase(PurchaseVoucherRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Authentication required',
                'error' => 'User not authenticated',
            ], 401);
        }

        try {
            $validated = $request->validated();

            // Get user's balance for debugging
            $balanceValue = CreditBalance::where('owner_id', (string) $user->id)
                ->where('owner_type', User::class)
                ->where('type', 'cash')
                ->value('balance');

            /** @var int $balanceInCents */
            $balanceInCents = is_numeric($balanceValue) ? (int) $balanceValue : 0;

            Log::info('Voucher purchase initiated', [
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
                'payment_method' => $validated['payment_method'] ?? PaymentMethod::STRIPE,
                'user_balance_cents' => $balanceInCents,
                'user_balance_euros' => $balanceInCents,
                'order_recovered_id' => $validated['order_recovered_id'] ?? null,
            ]);

            /** @var array{product_id: string, payment_method: string, stripe_payment_id?: string, balance_amount?: int, order_recovered_id?: string} $validatedData */
            $validatedData = [
                'product_id' => $validated['product_id'],
                'payment_method' => is_string($validated['payment_method'] ?? null) ? $validated['payment_method'] : PaymentMethod::STRIPE,
            ];

            if (array_key_exists('stripe_payment_id', $validated) && is_string($validated['stripe_payment_id'])) {
                $validatedData['stripe_payment_id'] = $validated['stripe_payment_id'];
            }

            if (array_key_exists('balance_amount', $validated) && is_numeric($validated['balance_amount'])) {
                // balance_amount is expected in cents from frontend
                $validatedData['balance_amount'] = (int) $validated['balance_amount'];
            }

            if (array_key_exists('order_recovered_id', $validated) && is_string($validated['order_recovered_id'])) {
                $validatedData['order_recovered_id'] = $validated['order_recovered_id'];
            }

            $result = $this->purchaseAction->execute($validatedData);

            // Determine response status based on payment method
            $status = match ($result['payment_method']) {
                'balance' => 201, // Created - payment completed
                'mixed', 'stripe' => 200, // OK - requires further action
                default => 200
            };

            Log::info('Voucher purchase processed', [
                'user_id' => $user->id,
                'order_id' => $result['order_id'],
                'payment_method' => $result['payment_method'],
                'status' => $result['payment_status'] ?? 'unknown', // TODO: review - payment_status might not exist
            ]);

            return response()->json(['data' => $result], $status);

        } catch (InsufficientBalanceException $e) {
            Log::warning('Insufficient balance for voucher purchase', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Insufficient balance for this purchase',
                'error' => $e->getMessage(),
            ], 422);

        } catch (InvalidPaymentMethodException $e) {
            Log::error('Invalid payment method', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Invalid payment method',
                'error' => $e->getMessage(),
            ], 422);

        } catch (Exception $e) {
            Log::error('Error processing voucher purchase', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'An error occurred while processing your purchase',
                'error' => app()->environment(['local', 'testing']) ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Retry a failed order recovery
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function retry(string $orderId, RecoverFailedVoucherAction $action): JsonResponse
    {
        try {
            $order = $action->execute($orderId);

            return response()->json([
                'success' => true,
                'message' => 'Order recovery initiated',
                'data' => [
                    'order' => new OrderResource($order),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
