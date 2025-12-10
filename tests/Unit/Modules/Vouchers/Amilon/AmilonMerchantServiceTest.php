<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use App\Settings\General\LocalizationSettings;
use Config;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonMerchantServiceTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private AmilonMerchantService $amilonService;

    protected string $baseUrl;

    protected string $contract_id;

    protected string $culture = 'FRA';

    private $mockAuthService;

    private string $mockToken = 'mock-token-123';

    protected function setUp(): void
    {
        parent::setUp();

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

        // Create real Action for proper translation handling
        $localizationSettings = app(LocalizationSettings::class);
        $createCategoryAction = new CreateOrUpdateTranslatedCategoryAction($localizationSettings);

        // Create the service with the mock auth service and real action
        $this->amilonService = new AmilonMerchantService($this->mockAuthService, $createCategoryAction);

        // Clear tables in correct order to respect foreign key constraints
        // First delete products that reference merchants
        Product::query()->delete();
        // Then clear the pivot table to avoid FK constraint violations
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        // Then delete merchants and categories
        Merchant::query()->delete();
        Category::query()->delete();

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

        $culture = 'pt-PT';

        Http::fake([
            '*' => Http::response([
                [
                    'Name' => 'Fnac',
                    'category' => 'Electronics',
                    'RetailerId' => 'FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                    'CountryISOAlpha3' => 'ITA',
                ],
                [
                    'Name' => 'Decathlon',
                    'category' => 'Sports',
                    'RetailerId' => 'DECA001',
                    'LongDescription' => 'Decathlon gift card',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                    'CountryISOAlpha3' => 'ITA',
                ],
            ], 200),
        ]);

        // Verify that the API will be called
        Http::assertNothingSent();

        $retailers = $this->amilonService->getMerchants('pt-PT');

        // Verify that the API was called with the token
        Http::assertSent(function (Request $request) use ($culture): bool {
            $expectedUrl = "{$this->baseUrl}/contracts/{$this->contract_id}/{$culture}/retailers";

            return $request->url() === $expectedUrl &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
        // Verify that the API was called only once
        Http::assertSentCount(1);

        $this->assertInstanceOf(Collection::class, $retailers);
        $this->assertNotEmpty($retailers, 'No retailers returned from service');
        $this->assertInstanceOf(Merchant::class, $retailers->first());

        /** @var Merchant $firstMerchant */
        $firstMerchant = $retailers->first();
        $this->assertNotNull($firstMerchant->name);
        $this->assertNotNull($firstMerchant->merchant_id);

        // Check specific values
        $this->assertEquals('Fnac', $firstMerchant->name);
        $this->assertEquals('FNAC001', $firstMerchant->merchant_id);
    }

    #[Test]
    public function it_returns_merchants_from_database_first_if_available(): void
    {
        // Arrange: Create merchant in database with PRT country (ISO Alpha-3 code)
        Merchant::create([
            'merchant_id' => 'DB001',
            'name' => 'DB Merchant',
            'country' => 'PRT',
            'description' => 'From database',
        ]);

        // Setup fake API (should not be called)
        Http::fake([
            '*' => Http::response([
                [
                    'Name' => 'Should Not Be Called',
                    'RetailerId' => 'API001',
                ],
            ], 200),
        ]);

        // Act
        $merchants = $this->amilonService->getMerchants('pt-PT');

        // Assert: API was NOT called
        Http::assertNothingSent();

        $this->assertInstanceOf(Collection::class, $merchants);
        $this->assertCount(1, $merchants);

        /** @var Merchant $merchant */
        $merchant = $merchants->first();
        $this->assertEquals('DB Merchant', $merchant->name);
        $this->assertEquals('DB001', $merchant->merchant_id);
    }

    #[Test]
    public function test_auth_error_refreshes_token_and_retries(): void
    {

        // Create a new mock for this specific test
        $testMockAuthService = Mockery::mock(AmilonAuthService::class);
        $testMockAuthService->shouldReceive('getAccessToken')
            ->andReturn($this->mockToken);
        $testMockAuthService->shouldReceive('refreshToken')
            ->once()
            ->andReturn('new-token-456');

        // Create real Action for proper translation handling
        $localizationSettings = app(LocalizationSettings::class);
        $createCategoryAction = new CreateOrUpdateTranslatedCategoryAction($localizationSettings);

        // Replace the service with one using our test-specific mock
        $this->amilonService = new AmilonMerchantService($testMockAuthService, $createCategoryAction);

        // Mock HTTP responses - first 401, then 200 after token refresh
        $culture = 'pt-PT';
        $uri = "{$this->baseUrl}/contracts/{$this->contract_id}/{$culture}/retailers";
        Http::fake([
            // First request fails with 401
            '*' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push([
                    [
                        'Name' => 'Fnac',
                        'category' => 'Electronics',
                        'RetailerId' => 'FNAC001',
                        'LongDescription' => 'Fnac gift card',
                        'ImageUrl' => 'https://example.com/fnac.jpg',
                        'CountryISOAlpha3' => 'ITA',
                    ],
                ], 200),
        ]);

        // Call the service method
        $retailers = $this->amilonService->getMerchants();

        // Assert the response structure
        $this->assertInstanceOf(Collection::class, $retailers);
        $this->assertNotEmpty($retailers);
        $this->assertInstanceOf(Merchant::class, $retailers[0]);

        // Verify that the API was called twice (once with old token, once with new token)
        Http::assertSentCount(2);

        // Verify the first call had the original token
        Http::assertSent(function (Request $request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });

        // Verify the second call had the new token
        Http::assertSent(function (Request $request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer new-token-456');
        });
    }

    #[Test]
    public function test_merchant_service_filter_by_name(): void
    {

        Http::fake([
            '*' => Http::response([
                [
                    'Name' => 'Electronics Store',
                    'RetailerId' => 'ELEC001',
                    'LongDescription' => 'Electronics merchant',
                    'ImageUrl' => 'https://example.com/electronics.jpg',
                    'CountryISOAlpha3' => 'ITA',
                ],
                [
                    'Name' => 'Fashion Store',
                    'RetailerId' => 'FASH001',
                    'LongDescription' => 'Fashion merchant',
                    'ImageUrl' => 'https://example.com/fashion.jpg',
                    'CountryISOAlpha3' => 'ITA',
                ],
            ], 200),
        ]);

        // Get all merchants first to populate database
        $allMerchants = $this->amilonService->getMerchants();
        $this->assertCount(2, $allMerchants);

        // Filter merchants by name from database
        $electronicsMerchants = Merchant::where('name', 'Electronics Store')->get();

        // Should only have electronics store
        $this->assertCount(1, $electronicsMerchants);
        $this->assertEquals('Electronics Store', $electronicsMerchants->first()->name);
    }

    #[Test]
    public function test_merchant_service_calculate_available_amounts(): void
    {
        $merchants = collect([
            new Merchant([
                'name' => 'Test Merchant 1',
                'merchant_id' => 'TEST001',
                'description' => 'Test merchant 1',
                'image_url' => 'https://example.com/test1.jpg',
            ]),
            new Merchant([
                'name' => 'Test Merchant 2',
                'merchant_id' => 'TEST002',
                'description' => 'Test merchant 2',
                'image_url' => 'https://example.com/test2.jpg',
            ]),
        ]);

        // Test with budget of 75 (should include 10, 25, 50, 75)
        $merchantsWithAmounts = $this->amilonService->calculateAvailableAmounts($merchants, 75.0);

        $this->assertCount(2, $merchantsWithAmounts);

        $firstMerchant = $merchantsWithAmounts->first();
        $this->assertEquals([10, 25, 50, 75], $firstMerchant->available_amounts);

        // Test with budget of 100 (should include 10, 25, 50, 100)
        $merchantsWithAmounts = $this->amilonService->calculateAvailableAmounts($merchants, 100.0);
        $firstMerchant = $merchantsWithAmounts->first();
        $this->assertEquals([10, 25, 50, 100], $firstMerchant->available_amounts);

        // Test with budget of 1000 (should include all standard amounts)
        $merchantsWithAmounts = $this->amilonService->calculateAvailableAmounts($merchants, 1000.0);
        $firstMerchant = $merchantsWithAmounts->first();
        $this->assertEquals([10, 25, 50, 100, 250, 500, 1000], $firstMerchant->available_amounts);

        // Test with budget of 5 (should include no amounts)
        $merchantsWithAmounts = $this->amilonService->calculateAvailableAmounts($merchants, 5.0);
        $firstMerchant = $merchantsWithAmounts->first();
        $this->assertEquals([5], $firstMerchant->available_amounts);
    }
}
