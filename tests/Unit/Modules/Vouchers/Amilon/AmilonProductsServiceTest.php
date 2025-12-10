<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\AmilonDatabaseCleanup;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonProductsServiceTest extends ProtectedRouteTestCase
{
    use AmilonDatabaseCleanup;

    private AmilonProductService $amilonService;

    private Merchant $merchant;

    protected string $baseUrl;

    protected string $contract_id;

    protected string $culture = 'FRA';

    private $mockAuthService;

    private string $mockToken = 'mock-token-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up database before each test
        $this->cleanupAmilonDatabase();

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
        $this->amilonService = new AmilonProductService($this->mockAuthService);

        // Generate a unique merchant ID for this test run to avoid conflicts
        $uniqueMerchantId = 'TEST_'.uniqid();
        $this->merchant = resolve(MerchantFactory::class)->create([
            'merchant_id' => $uniqueMerchantId,
        ]);

        // Ensure merchant is persisted
        $this->assertNotNull($this->merchant->id);
        $this->assertNotNull($this->merchant->merchant_id);

        // Clear cache before each test
        try {
            Cache::tags(['amilon'])->flush();
        } catch (Exception $e) {
            // If flush fails in Redis Cluster, continue without cache clearing
            // The test should still work with existing cache
        }
    }

    #[Test]
    public function test_fetch_products_returns_valid_json_structure(): void
    {
        // Mock HTTP response from Amilon API

        $contractId = config('services.amilon.contrat_id');
        // When merchant_id is passed, the service uses 'pt-PT' as culture
        $productsUri = "{$this->baseUrl}/contracts/{$contractId}/pt-PT/products/complete";

        Http::fake([
            $productsUri => Http::response([
                [
                    'ProductCode' => '123',
                    'Name' => 'Fnac Gift Card',
                    'category' => 'Electronics',
                    'LongDescription' => 'Fnac gift card for electronics',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                    'Price' => 50.00,  // This is in available_amounts
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCode' => $this->merchant->merchant_id,  // Changed from MerchantId
                    'MerchantCategory1' => null,
                    'MerchantCountry' => 'Portugal',
                ],
                [
                    'ProductCode' => '456',
                    'Name' => 'Decathlon Gift Card',
                    'category' => 'Sports',
                    'LongDescription' => 'Decathlon gift card for sports equipment',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                    'Price' => 30.00,  // Changed to an available amount
                    'NetPrice' => 27.00,
                    'Currency' => 'EUR',
                    'MerchantCode' => $this->merchant->merchant_id,  // Changed from MerchantId
                    'MerchantCategory1' => null,
                    'MerchantCountry' => 'Portugal',
                ],
            ], 200),
        ]);

        // Call the service method
        $products = $this->amilonService->getProducts($this->merchant->merchant_id);

        // Assert the response structure
        $this->assertInstanceOf(Collection::class, $products);
        $this->assertNotEmpty($products);

        // Get the first product
        $firstProduct = $products->first();

        // Check first product structure
        $this->assertInstanceOf(Product::class, $firstProduct);

        // Check if HTTP was called
        Http::assertSentCount(1);

        // Check specific values
        $this->assertEquals('123', $firstProduct->product_code);
        $this->assertEquals('Fnac Gift Card', $firstProduct->name);
        $this->assertEquals(null, $firstProduct->category_id);
        $this->assertEquals($this->merchant->merchant_id, $firstProduct->merchant_id);
        $this->assertEquals(5000, $firstProduct->price);     // 50 euros in cents
        $this->assertEquals(4500, $firstProduct->net_price);  // 45 euros in cents

        // Verify that the API was called with the token
        Http::assertSent(function ($request) use ($productsUri): bool {
            return $request->url() === $productsUri &&
                $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_api_down_returns_fallback_data_for_products(): void
    {

        // Create some products in the database for this merchant
        Product::factory()->count(3)->create([
            'merchant_id' => $this->merchant->merchant_id,
        ]);

        // Mock HTTP response for API down
        $contractId = config('services.amilon.contrat_id');
        $productsUri = "{$this->baseUrl}/contracts/{$contractId}/{$this->merchant->merchant_id}/products/complete";

        Http::fake([
            $productsUri => Http::response(null, 503),
        ]);

        // Call the service method
        $products = $this->amilonService->getProducts($this->merchant->merchant_id);

        // Assert fallback data is returned
        $this->assertInstanceOf(Collection::class, $products);
        $this->assertNotEmpty($products);
        $this->assertCount(3, $products);

        // Get the first product
        $firstProduct = $products->first();
        // Fallback data should have basic structure
        $this->assertInstanceOf(Product::class, $firstProduct);
        $this->assertEquals($this->merchant->merchant_id, $firstProduct->merchant_id);
    }
}
