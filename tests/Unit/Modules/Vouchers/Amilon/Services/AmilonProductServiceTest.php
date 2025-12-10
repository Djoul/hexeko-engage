<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Vouchers\Amilon\Services;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonProductServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AmilonProductService $productService;

    private MockObject $authService;

    protected function setUp(): void
    {
        parent::setUp();
        // Set up the required config
        config(['services.amilon.api_url' => 'https://api.amilon.test']);
        config(['services.amilon.contrat_id' => 'test-contract']);

        $this->authService = $this->createMock(AmilonAuthService::class);
        $this->authService->method('getAccessToken')->willReturn('test-token');

        $this->productService = new AmilonProductService($this->authService);

        // Activer le mode debug
        $this->withoutExceptionHandling();

    }

    #[Test]
    public function it_maps_merchant_id_from_api_response_to_existing_merchant(): void
    {
        // Clear cache to ensure fresh data
        Cache::tags(['amilon'])->flush();

        // Create a merchant in the database
        $merchantId = 'RETAILER_'.str_replace('-', '', Uuid::uuid4()->toString());
        resolve(MerchantFactory::class)->create([
            'merchant_id' => $merchantId,
        ]);

        // Mock API response with product data including MerchantId
        Http::fake([
            '*' => Http::response([
                [
                    'ProductCode' => 'PROD123_'.str_replace('-', '', Uuid::uuid4()->toString()),
                    'Name' => 'Test Product',
                    'MerchantCategory1' => null, // Category ID should be null or a valid UUID
                    'MerchantCode' => $merchantId, // This should map to merchant_id
                    'Price' => 50.00,
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Test description',
                    'ImageUrl' => 'https://example.com/image.jpg',
                ],
            ], 200),
        ]);

        $products = $this->productService->getProducts($merchantId);
        $this->assertCount(1, $products);

        // Verify the product was created with the correct merchant_id
        $product = Product::where('merchant_id', $merchantId)->first();
        $this->assertNotNull($product);
        $this->assertEquals($merchantId, $product->merchant_id);
    }

    #[Test]
    public function it_returns_empty_collection_when_merchant_does_not_exist_in_database(): void
    {
        // Clear cache to ensure fresh data
        Cache::tags(['amilon'])->flush();

        // Mock API response with product data for non-existent merchant
        Http::fake([
            '*/products/complete' => Http::response([
                [
                    'ProductCode' => 'PROD456_'.str_replace('-', '', Uuid::uuid4()->toString()),
                    'Name' => 'Another Test Product',
                    'MerchantCategory1' => null,
                    'MerchantCode' => 'NONEXISTENT123',
                    'Price' => 75.00,
                    'NetPrice' => 68.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Another test description',
                    'ImageUrl' => 'https://example.com/image2.jpg',
                ],
            ], 200),
        ]);

        // The service should handle the error gracefully and return empty collection
        $products = $this->productService->getProducts('NONEXISTENT123');

        // Since there's likely a FK constraint, no products should be created
        $this->assertCount(0, $products);

        // Verify no product was created in the database
        $product = Product::where('product_code', 'like', 'PROD456_%')->first();
        $this->assertNull($product);
    }

    #[Test]
    public function it_assigns_all_products_to_specified_merchant(): void
    {
        // Clear cache to ensure fresh data
        Cache::tags(['amilon'])->flush();

        // Create a merchant that we'll assign products to
        $retailerId = 'RETAILER1_'.strtoupper(str_replace('-', '', substr(Uuid::uuid4()->toString(), 0, 8)));
        resolve(MerchantFactory::class)->create(['merchant_id' => $retailerId]);

        // Mock API response with products from different merchants
        Http::fake([
            '*/products/complete' => Http::response([
                [
                    'ProductCode' => 'PROD1_'.str_replace('-', '', Uuid::uuid4()->toString()),
                    'Name' => 'Product 1',
                    'MerchantCategory1' => null,
                    'MerchantCode' => $retailerId, // Will be overridden by the service
                    'Price' => 20.00,
                    'NetPrice' => 22.50,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Product 1 description',
                    'ImageUrl' => 'https://example.com/prod1.jpg',
                ],
                [
                    'ProductCode' => 'PROD2_'.str_replace('-', '', Uuid::uuid4()->toString()),
                    'Name' => 'Product 2',
                    'MerchantCategory1' => null,
                    'MerchantCode' => $retailerId, // Will be overridden by the service
                    'Price' => 30.00,
                    'NetPrice' => 31.50,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Product 2 description',
                    'ImageUrl' => 'https://example.com/prod2.jpg',
                ],
            ], 200),
        ]);

        // The API returns all products but getProducts assigns them to the specified merchant
        $products = $this->productService->getProducts($retailerId);

        // All products are assigned the merchant_id passed as parameter
        $this->assertCount(2, $products);

        $product1 = Product::where('product_code', 'like', 'PROD1_%')->where('merchant_id', $retailerId)->first();
        $product2 = Product::where('product_code', 'like', 'PROD2_%')->where('merchant_id', $retailerId)->first();

        // Both products should have $retailerId as merchant_id because that's what was passed
        $this->assertEquals($retailerId, $product1->merchant_id);
        $this->assertEquals($retailerId, $product2->merchant_id);
    }
}
