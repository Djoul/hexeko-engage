<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Actions\Integrations\Vouchers\Amilon\SendNewCategorySlackNotificationAction;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonCategoryService;
use App\Settings\General\LocalizationSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonCategoryServiceTest extends ProtectedRouteTestCase
{
    private AmilonCategoryService $amilonCategoryService;

    private $mockAuthService;

    private string $mockToken = 'mock-token-123';

    protected string $baseUrl;

    private LocalizationSettings $localizationSettings;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for tests
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');

        $this->baseUrl = config('services.amilon.api_url').'/b2bwebapi/v1';

        // Create a mock for the auth service
        $this->mockAuthService = Mockery::mock(AmilonAuthService::class);

        $this->mockAuthService->shouldReceive('getAccessToken')
            ->zeroOrMoreTimes()
            ->andReturn($this->mockToken);

        $this->mockAuthService->shouldReceive('refreshToken')
            ->zeroOrMoreTimes()
            ->andReturn($this->mockToken);

        // Get real localization settings
        $this->localizationSettings = app(LocalizationSettings::class);

        // Create real Actions (not mocked) for proper translation handling
        $createCategoryAction = new CreateOrUpdateTranslatedCategoryAction($this->localizationSettings);
        $slackNotificationAction = new SendNewCategorySlackNotificationAction;

        // Create the service with all dependencies
        $this->amilonCategoryService = new AmilonCategoryService(
            $this->mockAuthService,
            $createCategoryAction,
            $slackNotificationAction
        );

        // Clear ALL cache to ensure clean test environment
        Cache::flush();

        // Clear database tables in correct order to avoid foreign key violations
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        DB::table('int_vouchers_amilon_products')->delete();
        DB::table('int_vouchers_amilon_categories')->delete();

    }

    /**
     * Helper method to create a category with proper translations.
     */
    private function createCategory(string $categoryId, string $categoryName): Category
    {
        $translatedName = [];
        foreach ($this->localizationSettings->available_locales as $locale) {
            $translatedName[$locale] = $categoryName;
        }

        return Category::create([
            'id' => $categoryId,
            'name' => $translatedName,
        ]);
    }

    #[Test]
    public function test_category_service_get_all_categories(): void
    {
        // Mock HTTP response from Amilon API
        $uri = "{$this->baseUrl}/retailers/categories";

        $catId1 = fake()->uuid();
        $catId2 = fake()->uuid();
        $catId3 = fake()->uuid();

        Http::fake([
            '*' => Http::response([
                [
                    'CategoryId' => $catId1,
                    'CategoryName' => 'Electronics',
                ],
                [
                    'CategoryId' => $catId2,
                    'CategoryName' => 'Books',
                ],
                [
                    'CategoryId' => $catId3,
                    'CategoryName' => 'Clothing',
                ],
            ], 200),
        ]);

        $categories = $this->amilonCategoryService->fetchCategoriesFromApi();

        $this->assertInstanceOf(Collection::class, $categories);
        $this->assertCount(3, $categories);

        // Verify structure
        $firstCategory = $categories->first();
        $this->assertArrayHasKey('CategoryId', $firstCategory);
        $this->assertArrayHasKey('CategoryName', $firstCategory);

        // Verify data
        $this->assertEquals($catId1, $firstCategory['CategoryId']);
        $this->assertEquals('Electronics', $firstCategory['CategoryName']);

        // Verify that the API was called with the token
        Http::assertSent(function (Request $request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_category_service_get_category_by_id(): void
    {
        // Create test category in database with valid UUID
        $categoryId = fake()->uuid();
        $this->createCategory($categoryId, 'Test Electronics');

        // Test getting existing category
        $foundCategory = $this->amilonCategoryService->getCategoryById($categoryId);
        $this->assertNotNull($foundCategory);
        $this->assertEquals($categoryId, $foundCategory->id);
        $this->assertEquals('Test Electronics', $foundCategory->getTranslation('name', app()->getLocale()));

        // Test getting non-existing category with valid UUID
        $nonExistingId = fake()->uuid();
        $notFoundCategory = $this->amilonCategoryService->getCategoryById($nonExistingId);
        $this->assertNull($notFoundCategory);
    }

    #[Test]
    public function test_category_service_validate_category_exists(): void
    {
        // Create test category in database with valid UUID
        $categoryId = fake()->uuid();
        $this->createCategory($categoryId, 'Test Electronics');

        // Test existing category
        $this->assertTrue($this->amilonCategoryService->validateCategoryExists($categoryId));

        // Test non-existing category with valid UUID
        $nonExistingId = fake()->uuid();
        $this->assertFalse($this->amilonCategoryService->validateCategoryExists($nonExistingId));
    }

    #[Test]
    public function test_category_service_handles_api_failure(): void
    {
        // Mock HTTP response for API down
        $uri = "{$this->baseUrl}/retailers/categories";
        Http::fake([
            $uri => Http::response(null, 503),
        ]);

        // Call the service method
        $categories = $this->amilonCategoryService->getCategories();

        // Assert fallback behavior
        $this->assertInstanceOf(Collection::class, $categories);
        // Should return empty collection when API is down and no database entries
        $this->assertTrue($categories->isEmpty());

        // Verify that the API was called with the token
        Http::assertSent(function (Request $request) use ($uri): bool {
            return $request->url() === $uri &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_category_service_returns_database_fallback(): void
    {
        // Create categories in database with valid UUIDs
        $cat1Id = fake()->uuid();
        $cat2Id = fake()->uuid();
        $this->createCategory($cat1Id, 'Database Electronics');
        $this->createCategory($cat2Id, 'Database Books');

        // Mock HTTP response for API down
        $uri = "{$this->baseUrl}/retailers/categories";
        Http::fake([
            $uri => Http::response(null, 503),
        ]);

        // Call the service method
        $categories = $this->amilonCategoryService->getCategories();

        // Should return database categories as fallback
        $this->assertInstanceOf(Collection::class, $categories);
        $this->assertCount(2, $categories);

        $firstCategory = $categories->first();
        $this->assertIsArray($firstCategory);
        $this->assertEquals($cat1Id, $firstCategory['CategoryId']);
        $this->assertEquals('Database Electronics', $firstCategory['CategoryName']);
    }
}
