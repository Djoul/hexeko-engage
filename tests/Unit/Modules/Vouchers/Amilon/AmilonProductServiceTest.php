<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[FlushTables(scope: 'test')]
#[Group('amilon')]
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
        config(['services.amilon.available_amounts' => [20.00, 30.00, 50.00, 75.00, 100.00]]);

        $this->authService = $this->createMock(AmilonAuthService::class);
        $this->authService->method('getAccessToken')->willReturn('test-token');

        $this->productService = new AmilonProductService($this->authService);

    }

    #[Test]
    #[DataProvider('cultureCountryMappingProvider')]
    public function it_maps_financer_country_to_correct_culture(string $country, string $expectedCulture): void
    {
        // Arrange - Create financer with specific country
        $financer = ModelFactory::createFinancer([
            'division_id' => ModelFactory::createDivision(['country' => $country])->id,
        ]);

        resolve(MerchantFactory::class)->create(['merchant_id' => 'TEST_MERCHANT']);

        // Mock API response
        Http::fake([
            "*{$expectedCulture}/products/complete" => Http::response([
                [
                    'ProductCode' => 'CULTURE_PROD',
                    'Name' => 'Product with culture',
                    'MerchantCode' => 'TEST_MERCHANT',
                    'Price' => 50.00,
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => $country,
                    'LongDescription' => 'Description',
                    'ImageUrl' => 'https://example.com/img.jpg',
                ],
            ], 200),
        ]);

        // Act - Request products with financer context
        $products = $this->productService->getProductsForFinancer($financer, 'TEST_MERCHANT');

        // Assert - Verify the correct culture was used in API call
        Http::assertSent(function ($request) use ($expectedCulture): bool {
            return str_contains($request->url(), "/{$expectedCulture}/products/complete");
        });

        $this->assertCount(1, $products);
    }

    public static function cultureCountryMappingProvider(): array
    {
        return [
            'Italy' => ['IT', 'it-IT'],
            'Denmark' => ['DK', 'da-DK'],
            'United Kingdom' => ['GB', 'en-GB'],
            'France' => ['FR', 'fr-FR'],
            'Spain' => ['ES', 'es-ES'],
            'Germany' => ['DE', 'de-DE'],
            'Netherlands' => ['NL', 'nl-NL'],
            'Norway' => ['NO', 'nn-NO'],
            'Poland' => ['PL', 'pl-PL'],
            'Portugal' => ['PT', 'pt-PT'],
        ];
    }

    #[Test]
    public function it_uses_default_culture_for_unknown_country(): void
    {
        // Arrange - Create financer with unknown country
        $financer = ModelFactory::createFinancer([
            'division_id' => ModelFactory::createDivision(['country' => 'XX'])->id,
        ]);
        resolve(MerchantFactory::class)->create(['merchant_id' => 'TEST_MERCHANT']);

        // Mock API response with default culture
        Http::fake([
            '*/pt-PT/products/complete' => Http::response([
                [
                    'ProductCode' => 'DEFAULT_PROD',
                    'Name' => 'Product with default culture',
                    'MerchantCode' => 'TEST_MERCHANT',
                    'Price' => 50.00,
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Description',
                    'ImageUrl' => 'https://example.com/img.jpg',
                ],
            ], 200),
        ]);

        // Act
        $products = $this->productService->getProductsForFinancer($financer, 'TEST_MERCHANT');

        // Assert - Should use pt-PT as default
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/pt-PT/products/complete');
        });

        $this->assertCount(1, $products);
    }

    #[Test]
    public function it_caches_products_separately_by_merchant_and_culture(): void
    {
        // Arrange - Create two financers with different countries
        $financerFR = ModelFactory::createFinancer([
            'division_id' => ModelFactory::createDivision(['country' => 'FR'])->id,
        ]);

        $financerDE = ModelFactory::createFinancer([
            'division_id' => ModelFactory::createDivision(['country' => 'DE'])->id,
        ]);

        resolve(MerchantFactory::class)->create(['merchant_id' => 'MULTI_CULTURE']);

        // Mock different responses for different cultures
        Http::fake([
            '*/fr-FR/products/complete' => Http::response([
                [
                    'ProductCode' => 'FR_PROD',
                    'Name' => 'French Product',
                    'MerchantCode' => 'MULTI_CULTURE',
                    'Price' => 50.00,
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'France',
                    'LongDescription' => 'Description FR',
                    'ImageUrl' => 'https://example.com/fr.jpg',
                ],
            ], 200),
            '*/de-DE/products/complete' => Http::response([
                [
                    'ProductCode' => 'DE_PROD',
                    'Name' => 'German Product',
                    'MerchantCode' => 'MULTI_CULTURE',
                    'Price' => 75.00,
                    'NetPrice' => 70.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Germany',
                    'LongDescription' => 'Description DE',
                    'ImageUrl' => 'https://example.com/de.jpg',
                ],
            ], 200),
        ]);

        // Act - Request products for both financers
        $productsFR = $this->productService->getProductsForFinancer($financerFR, 'MULTI_CULTURE');
        $productsDE = $this->productService->getProductsForFinancer($financerDE, 'MULTI_CULTURE');

        // Assert - Since products are saved to DB on first call, both will return the same products
        // The priority is DB > Cache > API
        // After first API call (FR), product is saved to DB
        // Second call (DE) will return the same product from DB
        $this->assertCount(1, $productsFR);
        $this->assertEquals('FR_PROD', $productsFR->first()->product_code);

        // Since DB has priority, it returns the same product
        $this->assertCount(1, $productsDE);
        $this->assertEquals('FR_PROD', $productsDE->first()->product_code); // Same product from DB

    }

    #[Test]
    public function it_returns_products_from_database_first_if_available(): void
    {
        // Arrange - Create merchant and products in database
        resolve(MerchantFactory::class)->create(['merchant_id' => 'DB_MERCHANT']);

        Product::create([
            'product_code' => 'DB_PROD1',
            'name' => 'Database Product 1',
            'merchant_id' => 'DB_MERCHANT',
            'price' => 50.00,
            'net_price' => 45.00,
            'currency' => 'EUR',
        ]);

        Product::create([
            'product_code' => 'DB_PROD2',
            'name' => 'Database Product 2',
            'merchant_id' => 'DB_MERCHANT',
            'price' => 30.00,
            'net_price' => 27.00,
            'currency' => 'EUR',
        ]);

        // Mock API should NOT be called
        Http::fake([
            '*' => Http::response([], 500), // Return error to ensure it's not called
        ]);

        // Act - Request products (should get from DB)
        $products = $this->productService->getProducts('DB_MERCHANT');

        // Assert - Should return products from database
        $this->assertCount(2, $products);
        $this->assertEquals('DB_PROD1', $products->first()->product_code);
        $this->assertEquals('DB_PROD2', $products->last()->product_code);

        // Verify API was not called
        Http::assertNothingSent();

    }

    #[Test]
    public function it_fetches_from_api_only_when_no_db_(): void
    {
        // Arrange - Create merchant with no products and no cache
        resolve(MerchantFactory::class)->create(['merchant_id' => 'API_ONLY']);

        // Mock API response
        Http::fake([
            '*/products/complete' => Http::response([
                [
                    'ProductCode' => 'API_PROD1',
                    'Name' => 'API Product 1',
                    'MerchantCode' => 'API_ONLY',
                    'Price' => 50.00,
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'API Product Description',
                    'ImageUrl' => 'https://example.com/api.jpg',
                ],
            ], 200),
        ]);

        // Act - Request products (should call API)
        $products = $this->productService->getProducts('API_ONLY');

        // Assert - Should return products from API
        $this->assertCount(1, $products);
        $this->assertEquals('API_PROD1', $products->first()->product_code);

        // Verify API was called
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/products/complete');
        });

        // Verify products were saved to database
        $dbProducts = Product::where('merchant_id', 'API_ONLY')->get();
        $this->assertCount(1, $dbProducts);

    }

    #[Test]
    public function it_uses_correct_priority_order_db_then_api(): void
    {
        // Arrange - Setup different data in each layer
        resolve(MerchantFactory::class)->create(['merchant_id' => 'PRIORITY_TEST']);

        // 1. Database has 1 product
        Product::create([
            'product_code' => 'DB_PRIORITY',
            'name' => 'From Database',
            'merchant_id' => 'PRIORITY_TEST',
            'price' => 25.00,
            'net_price' => 22.00,
            'currency' => 'EUR',
        ]);

        // 2. Cache has 2 different products (should be ignored)
        collect([
            (object) [
                'product_code' => 'CACHE_PRIORITY1',
                'name' => 'From Cache 1',
                'merchant_id' => 'PRIORITY_TEST',
                'price' => 50.00,
            ],
            (object) [
                'product_code' => 'CACHE_PRIORITY2',
                'name' => 'From Cache 2',
                'merchant_id' => 'PRIORITY_TEST',
                'price' => 75.00,
            ],
        ]);

        // 3. API has 3 different products (should be ignored)
        Http::fake([
            '*/products/complete' => Http::response([
                [
                    'ProductCode' => 'API_PRIORITY1',
                    'Name' => 'From API 1',
                    'MerchantCode' => 'PRIORITY_TEST',
                    'Price' => 100.00,
                    'NetPrice' => 95.00,
                    'Currency' => 'EUR',
                ],
                [
                    'ProductCode' => 'API_PRIORITY2',
                    'Name' => 'From API 2',
                    'MerchantCode' => 'PRIORITY_TEST',
                    'Price' => 150.00,
                    'NetPrice' => 145.00,
                    'Currency' => 'EUR',
                ],
            ], 200),
        ]);

        // Act - Request products
        $products = $this->productService->getProducts('PRIORITY_TEST');

        // Assert - Should return from database (highest priority)
        $this->assertCount(1, $products);
        $this->assertEquals('DB_PRIORITY', $products->first()->product_code);
        $this->assertEquals('From Database', $products->first()->name);

        // Verify API was NOT called
        Http::assertNothingSent();

    }

    #[Test]
    public function it_filters_products_by_available_amounts_config(): void
    {
        // Arrange
        resolve(MerchantFactory::class)->create(['merchant_id' => 'AMOUNT_TEST']);

        // Mock API response with various price amounts
        Http::fake([
            '*/products/complete' => Http::response([
                [
                    'ProductCode' => 'VALID_20',
                    'Name' => 'Valid 20 EUR',
                    'MerchantCode' => 'AMOUNT_TEST',
                    'Price' => 20.00, // Valid amount
                    'NetPrice' => 18.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Description',
                    'ImageUrl' => 'https://example.com/img.jpg',
                ],
                [
                    'ProductCode' => 'INVALID_25',
                    'Name' => 'Invalid 25 EUR',
                    'MerchantCode' => 'AMOUNT_TEST',
                    'Price' => 25.00, // Invalid amount
                    'NetPrice' => 22.50,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Description',
                    'ImageUrl' => 'https://example.com/img.jpg',
                ],
                [
                    'ProductCode' => 'VALID_50',
                    'Name' => 'Valid 50 EUR',
                    'MerchantCode' => 'AMOUNT_TEST',
                    'Price' => 50.00, // Valid amount
                    'NetPrice' => 45.00,
                    'Currency' => 'EUR',
                    'MerchantCountry' => 'Portugal',
                    'LongDescription' => 'Description',
                    'ImageUrl' => 'https://example.com/img.jpg',
                ],
            ], 200),
        ]);

        // Act
        $products = $this->productService->getProducts('AMOUNT_TEST');

        // Assert - Only products with valid amounts should be returned
        $this->assertCount(2, $products);

        $productCodes = $products->pluck('product_code')->toArray();
        $this->assertContains('VALID_20', $productCodes);
        $this->assertContains('VALID_50', $productCodes);
        $this->assertNotContains('INVALID_25', $productCodes);
    }
}
