<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonCreateOrdersServiceTest extends ProtectedRouteTestCase
{
    private AmilonOrderService $amilonService;

    protected User $authUser;

    protected string $baseUrl;

    protected string $contract_id;

    protected string $culture = 'FRA';

    private $mockAuthService;

    private string $mockToken = 'mock-token-123';

    protected function setUp(): void
    {

        parent::setUp();

        $this->authUser = $this->createAuthUser();
        // Set up config for tests
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', 'test-contract');

        $this->baseUrl = config('services.amilon.api_url').'/b2bwebapi/v1';

        $this->contract_id = config('services.amilon.contrat_id');

        // Create a mock for the auth service
        $this->mockAuthService = Mockery::mock(AmilonAuthService::class);

        $this->mockAuthService->shouldReceive('getAccessToken')
            ->zeroOrMoreTimes()
            ->andReturn($this->mockToken);

        $this->mockAuthService->shouldReceive('refreshToken')
            ->zeroOrMoreTimes()
            ->andReturn($this->mockToken);

        // Create the service with the mock auth service
        $this->amilonService = new AmilonOrderService($this->mockAuthService);

        // Clear cache before each test
        Cache::forget('amilon_products');
    }

    #[Test]
    public function test_payload_contains_unique_external_order_id(): void
    {
        // First order
        $firstOrderId = $this->amilonService->generateExternalOrderId();

        // Second order
        $secondOrderId = $this->amilonService->generateExternalOrderId();

        // Assert that the order IDs are different
        $this->assertNotEquals($firstOrderId, $secondOrderId);

        // Assert that the order IDs match the expected format (e.g., ENGAGE-YYYY-019767bf-f834-70b2-b3f2-bc09e9acd879)
        $this->assertMatchesRegularExpression(
            '/^ENGAGE-\d{4}-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $firstOrderId
        );
        $this->assertMatchesRegularExpression(
            '/^ENGAGE-\d{4}-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $secondOrderId
        );
    }

    #[Test]
    public function test_payload_is_valid_json_for_create_order(): void
    {
        // Create merchant and product first to get their IDs
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        // Mock HTTP response
        $createOrderUri = "{$this->baseUrl}/Orders/create/{$this->contract_id}";

        Http::fake([
            $createOrderUri => Http::response([
                [
                    'externalOrderId' => 'ENGAGE-2025-TEST123',
                    'orderDate' => '2025-06-12T13:04:55.019Z',
                    'grossAmount' => 100.00,
                    'netAmount' => 80.00,
                    'totalRequestedCodes' => 2,
                    'orderStatus' => 'created',
                    'purchaseOrder' => 'PO-123',
                    'vouchers' => [
                        [
                            'voucherLink' => 'https://my-gate.amilon.eu/?pin=XYZ123',
                            'validityStartDate' => '2025-06-12T13:04:55.019Z',
                            'validityEndDate' => '2025-06-12T13:04:55.019Z',
                            'productId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                            'cardCode' => 'CARD123',
                            'pin' => 'PIN123',
                            'retailerId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                            'retailerName' => 'Test Retailer',
                            'retailerCountry' => 'France',
                            'retailerCountryISOAlpha3' => 'FRA',
                            'name' => 'John',
                            'surname' => 'Doe',
                            'email' => 'john@example.com',
                            'dedication' => 'Happy Birthday!',
                            'orderFrom' => 'John',
                            'orderTo' => 'Jane',
                            'amount' => 50.00,
                            'deleted' => false,
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Call the service method (product already created above)
        $quantity = 2;
        $externalOrderId = 'ENGAGE-2025-TEST123';
        $userId = $this->authUser->id;
        $paymentId = 'payment-123';

        $this->amilonService->createOrder($product, $quantity, $externalOrderId, $userId, $paymentId);

        // Verify that the API was called with the correct payload
        Http::assertSent(function ($request) use ($product, $quantity, $externalOrderId, $createOrderUri): bool {
            $data = $request->data();

            // Check that the request URL is correct
            $urlCorrect = $request->url() === $createOrderUri;

            // Check that the request has the correct headers
            $headersCorrect = $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);

            // Check that the request has the correct payload
            $payloadCorrect = isset($data['externalOrderId']) && $data['externalOrderId'] === $externalOrderId &&
                isset($data['orderRows']) && is_array($data['orderRows']) && count($data['orderRows']) > 0 &&
                isset($data['orderRows'][0]['productId']) && $data['orderRows'][0]['productId'] === $product->product_code &&
                isset($data['orderRows'][0]['quantity']) && (int) $data['orderRows'][0]['quantity'] === $quantity;

            return $urlCorrect && $headersCorrect && $payloadCorrect;
        });
    }

    #[Test]
    public function test_create_order_returns_valid_voucher_url(): void
    {
        // Mock HTTP response
        $createOrderUri = "{$this->baseUrl}/Orders/create/{$this->contract_id}";
        Http::fake([
            $createOrderUri => Http::response([
                [
                    'externalOrderId' => 'ENGAGE-2025-TEST123',
                    'orderDate' => '2025-06-12T13:04:55.019Z',
                    'grossAmount' => 100.00,
                    'netAmount' => 80.00,
                    'totalRequestedCodes' => 2,
                    'orderStatus' => 'created',
                    'purchaseOrder' => 'PO-123',
                    'vouchers' => [
                        [
                            'voucherLink' => 'https://my-gate.amilon.eu/?pin=XYZ123',
                            'validityStartDate' => '2025-06-12T13:04:55.019Z',
                            'validityEndDate' => '2025-06-12T13:04:55.019Z',
                            'productId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                            'cardCode' => 'CARD123',
                            'pin' => 'PIN123',
                            'retailerId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                            'retailerName' => 'Test Retailer',
                            'retailerCountry' => 'France',
                            'retailerCountryISOAlpha3' => 'FRA',
                            'name' => 'John',
                            'surname' => 'Doe',
                            'email' => 'john@example.com',
                            'dedication' => 'Happy Birthday!',
                            'orderFrom' => 'John',
                            'orderTo' => 'Jane',
                            'amount' => 50.00,
                            'deleted' => false,
                        ],
                    ],
                ],
            ], 200),
        ]);

        // Call the service method
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();
        $quantity = 2;
        $externalOrderId = 'ENGAGE-2025-TEST123';
        $userId = $this->authUser->id;
        $paymentId = 'payment-123';

        $result = $this->amilonService->createOrder($product, $quantity, $externalOrderId, $userId, $paymentId);

        // Assert the response structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('merchant_id', $result);
        $this->assertArrayHasKey('amount', $result);
        $this->assertArrayHasKey('external_order_id', $result);
        $this->assertArrayHasKey('order_id', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('price_paid', $result);
        $this->assertArrayHasKey('voucher_url', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('payment_id', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('order_date', $result);
        $this->assertArrayHasKey('gross_amount', $result);
        $this->assertArrayHasKey('net_amount', $result);
        $this->assertArrayHasKey('total_requested_codes', $result);
        $this->assertArrayHasKey('order_status', $result);

        // Assert the items structure if present
        if (! empty($result['items'])) {
            $this->assertIsArray($result['items']);
        }

        // Assert the voucher_url is valid if present
        if (! empty($result['voucher_url'])) {
            $this->assertStringStartsWith('https://', $result['voucher_url']);
        }
    }

    #[Test]
    public function test_order_creation_under_3_seconds(): void
    {
        // Create a merchant and product for the test
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        // Mock HTTP response
        $createOrderUri = "{$this->baseUrl}/Orders/create/{$this->contract_id}";
        Http::fake([
            $createOrderUri => Http::response([
                'externalOrderId' => 'ENGAGE-2025-TEST123',
                'orderDate' => '2025-06-12T21:22:12.058Z',
                'grossAmount' => 100,
                'netAmount' => 80,
                'totalRequestedCodes' => 2,
                'orderStatus' => 'created',
                'purchaseOrder' => 'PO-123',
                'vouchers' => [
                    [
                        'voucherLink' => 'https://my-gate.amilon.eu/?pin=XYZ123',
                        'productId' => $product->id,  // Use actual product ID
                        'validityStartDate' => '2025-06-12T13:04:55.019Z',
                        'validityEndDate' => '2025-06-12T13:04:55.019Z',
                        'cardCode' => 'CARD123',
                        'pin' => 'PIN123',
                        'retailerId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                        'retailerName' => 'Test Retailer',
                        'retailerCountry' => 'France',
                        'retailerCountryISOAlpha3' => 'FRA',
                        'amount' => 50.00,
                        'deleted' => false,
                    ],
                ],
            ], 200),
        ]);

        // Call the service method (product already created above)
        $quantity = 2;
        $externalOrderId = 'ENGAGE-2025-TEST123';
        $userId = $this->authUser->id;
        $paymentId = 'payment-123';

        $startTime = microtime(true);
        $this->amilonService->createOrder($product, $quantity, $externalOrderId, $userId, $paymentId);
        $endTime = microtime(true);

        // Calculate execution time in seconds
        $executionTime = $endTime - $startTime;

        // Assert that the execution time is under 3 seconds
        $this->assertLessThan(3.0, $executionTime, "Order creation took more than 3 seconds ($executionTime seconds)");
    }

    #[Test]
    public function test_order_creation_handles_api_errors(): void
    {
        // Mock HTTP response for API error
        $createOrderUri = "{$this->baseUrl}/Orders/create/{$this->contract_id}";
        Http::fake([
            $createOrderUri => Http::response([
                'error' => 'Invalid product ID',
                'message' => 'The specified retailer ID does not exist',
            ], 400),
        ]);

        // Call the service method
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();
        $amount = 50;
        $externalOrderId = 'ENGAGE-2025-TEST123';

        // Expect an exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to create Amilon order');

        $this->amilonService->createOrder($product, $amount, $externalOrderId, $this->authUser->id);
    }

    #[Test]
    public function test_order_creation_handles_auth_error_and_retries(): void
    {
        // Create merchant and product first to get their IDs
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();

        Cache::tags(['amilon'])->forget('amilon_token');
        // Mock HTTP responses - first 401, then 200 after token refresh
        $createOrderUri = "{$this->baseUrl}/Orders/create/{$this->contract_id}";
        Http::fake([
            $createOrderUri => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401) // First request fails with 401
                ->push([ // Second request succeeds with 200
                    'externalOrderId' => 'ENGAGE-2025-TEST123',
                    'orderDate' => '2025-06-12T13:04:55.019Z',
                    'grossAmount' => 100.00,
                    'netAmount' => 80.00,
                    'totalRequestedCodes' => 2,
                    'orderStatus' => 'created',
                    'purchaseOrder' => 'PO-123',
                    'vouchers' => [
                        [
                            'voucherLink' => 'https://my-gate.amilon.eu/?pin=XYZ123',
                            'validityStartDate' => '2025-06-12T13:04:55.019Z',
                            'validityEndDate' => '2025-06-12T13:04:55.019Z',
                            'productId' => $product->id,
                            'cardCode' => 'CARD123',
                            'pin' => 'PIN123',
                            'retailerId' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                            'retailerName' => 'Test Retailer',
                            'retailerCountry' => 'France',
                            'retailerCountryISOAlpha3' => 'FRA',
                            'name' => 'John',
                            'surname' => 'Doe',
                            'email' => 'john@example.com',
                            'dedication' => 'Happy Birthday!',
                            'orderFrom' => 'John',
                            'orderTo' => 'Jane',
                            'amount' => 50.00,
                            'deleted' => false,
                        ],
                    ],
                ], 200),
        ]);

        // Call the service method (product already created above)
        $amount = 50;
        $externalOrderId = 'ENGAGE-2025-TEST123';

        $result = $this->amilonService->createOrder($product, $amount, $externalOrderId, $this->authUser->id);

        // Assert the response structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('voucher_url', $result);

        // Verify that the API was called twice (once with old token, once with new token)
        Http::assertSentCount(2);
    }
}
