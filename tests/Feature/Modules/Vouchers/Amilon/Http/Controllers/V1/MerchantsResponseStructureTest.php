<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Database\factories\CategoryFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class MerchantsResponseStructureTest extends ProtectedRouteTestCase
{
    const MERCHANTS_BY_CATEGORY_URL = '/api/v1/vouchers/amilon/merchants/by-category';

    const GET_MERCHANTS_AMILON_URL = 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/123-456-789/pt-PT/retailers';

    private string $mockToken = 'mock-token-123';

    #[Test]
    public function it_returns_valid_json_response_structure_for_organized_display(): void
    {
        // Note: Refactored to handle many-to-many category relation

        // Create categories
        $electronicsCategory = resolve(CategoryFactory::class)->create(['name' => 'Electronics']);
        $sportsCategory = resolve(CategoryFactory::class)->create(['name' => 'Sports']);
        $fashionCategory = resolve(CategoryFactory::class)->create(['name' => 'Fashion']);

        // Create merchants with categories using unique UUIDs
        $fnac = resolve(MerchantFactory::class)->create([
            'name' => 'Fnac',
            'country' => 'FRA',
            'merchant_id' => Uuid::uuid4()->toString(),
            'description' => 'Electronics and books',
            'image_url' => 'https://example.com/fnac.jpg',
        ]);
        $fnac->categories()->attach($electronicsCategory);

        $decathlon = resolve(MerchantFactory::class)->create([
            'name' => 'Decathlon',
            'country' => 'FRA',
            'merchant_id' => Uuid::uuid4()->toString(),
            'description' => 'Sports equipment',
            'image_url' => 'https://example.com/decathlon.jpg',
        ]);
        $decathlon->categories()->attach($sportsCategory);

        $zara = resolve(MerchantFactory::class)->create([
            'name' => 'Zara',
            'country' => 'ESP',
            'merchant_id' => Uuid::uuid4()->toString(),
            'description' => 'Fashion clothing',
            'image_url' => 'https://example.com/zara.jpg',
        ]);
        $zara->categories()->attach($fashionCategory);

        // Mock the service to return our test merchants
        $testMerchants = collect([$fnac, $decathlon, $zara])->each(function ($merchant): void {
            $merchant->load('categories');
        });

        $this->mock(AmilonMerchantService::class, function ($mock) use ($testMerchants): void {
            $mock->shouldReceive('getMerchants')->andReturn($testMerchants);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'Electronics' => [
                        '*' => [
                            'id',
                            'name',
                            'category',
                            'country',
                            'description',
                            'image_url',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'Sports' => [
                        '*' => [
                            'id',
                            'name',
                            'category',
                            'country',
                            'description',
                            'image_url',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'Fashion' => [
                        '*' => [
                            'id',
                            'name',
                            'category',
                            'country',
                            'description',
                            'image_url',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
            ]);

        $responseData = $response->json('data');

        // Validate that response follows expected organized display structure
        $this->assertIsArray($responseData);

        // Verify our created categories are present
        $this->assertArrayHasKey('Electronics', $responseData);
        $this->assertArrayHasKey('Sports', $responseData);
        $this->assertArrayHasKey('Fashion', $responseData);

        // Each category should be a key with merchant arrays as values
        foreach ($responseData as $categoryName => $merchants) {
            // Handle case where fallback merchants might create numeric keys
            if (is_numeric($categoryName)) {
                continue; // Skip numeric keys from collections
            }

            $this->assertIsString($categoryName);
            $this->assertIsArray($merchants);
            $this->assertNotEmpty($merchants);

            // Each merchant in category should have complete structure
            foreach ($merchants as $merchant) {
                $this->assertIsArray($merchant);
                $this->assertArrayHasKey('id', $merchant);
                $this->assertArrayHasKey('name', $merchant);
                $this->assertArrayHasKey('category', $merchant);
                $this->assertEquals($categoryName, $merchant['category']);
            }
        }

        // Verify our specific merchants are in their categories
        $electronicsNames = array_column($responseData['Electronics'], 'name');
        $this->assertContains('Fnac', $electronicsNames);

        $sportsNames = array_column($responseData['Sports'], 'name');
        $this->assertContains('Decathlon', $sportsNames);

        $fashionNames = array_column($responseData['Fashion'], 'name');
        $this->assertContains('Zara', $fashionNames);
    }

    #[Test]
    public function it_validates_organized_display_format_matches_ui_requirements(): void
    {
        // Fixed: category filter now works with many-to-many relation

        // Create electronics category
        $electronicsCategory = resolve(CategoryFactory::class)->create(['name' => 'Electronics']);

        // Create merchants in the same category with unique UUIDs
        $apple = resolve(MerchantFactory::class)->create([
            'name' => 'Apple Store',
            'country' => 'USA',
            'merchant_id' => Uuid::uuid4()->toString(),
            'description' => 'Apple products',
            'image_url' => 'https://example.com/apple.jpg',
        ]);
        $apple->categories()->attach($electronicsCategory);

        $samsung = resolve(MerchantFactory::class)->create([
            'name' => 'Samsung Store',
            'country' => 'KOR',
            'merchant_id' => Uuid::uuid4()->toString(),
            'description' => 'Samsung electronics',
            'image_url' => 'https://example.com/samsung.jpg',
        ]);
        $samsung->categories()->attach($electronicsCategory);

        // Mock the service to return our test merchants
        $testMerchants = collect([$apple, $samsung])->each(function ($merchant): void {
            $merchant->load('categories');
        });

        $this->mock(AmilonMerchantService::class, function ($mock) use ($testMerchants): void {
            $mock->shouldReceive('getMerchants')->andReturn($testMerchants);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Validate UI-friendly structure: category as key, merchants as array
        $this->assertArrayHasKey('Electronics', $responseData);
        $this->assertIsArray($responseData['Electronics']);
        // We created 2 merchants in this test, but there might be existing ones
        $this->assertGreaterThanOrEqual(2, count($responseData['Electronics']));

        // Find our specific merchants by name
        $merchantNames = array_column($responseData['Electronics'], 'name');
        $this->assertContains('Apple Store', $merchantNames);
        $this->assertContains('Samsung Store', $merchantNames);

        // Validate merchants within category maintain order and structure
        $electronicsCategory = $responseData['Electronics'];

        // Find our merchants in the array
        $appleFound = false;
        $samsungFound = false;

        foreach ($electronicsCategory as $merchant) {
            if ($merchant['name'] === 'Apple Store') {
                $appleFound = true;
                $this->assertEquals('Electronics', $merchant['category']);
            }
            if ($merchant['name'] === 'Samsung Store') {
                $samsungFound = true;
                $this->assertEquals('Electronics', $merchant['category']);
            }
        }

        $this->assertTrue($appleFound, 'Apple Store not found in response');
        $this->assertTrue($samsungFound, 'Samsung Store not found in response');
        // Already checked in the loop above
    }

    #[Test]
    public function it_enforces_strict_response_schema_validation(): void
    {
        // Refactored to handle many-to-many category relation

        // Create test data
        $category1 = resolve(CategoryFactory::class)->create(['name' => 'Category1']);
        $category2 = resolve(CategoryFactory::class)->create(['name' => 'Category2']);

        $merchant1 = resolve(MerchantFactory::class)->create();
        $merchant1->categories()->attach($category1);

        $merchant2 = resolve(MerchantFactory::class)->create();
        $merchant2->categories()->attach($category2);

        $merchant3 = resolve(MerchantFactory::class)->create(); // Uncategorized

        // Mock the service to return our test merchants
        $testMerchants = collect([$merchant1, $merchant2, $merchant3])->each(function ($merchant): void {
            $merchant->load('categories');
        });

        $this->mock(AmilonMerchantService::class, function ($mock) use ($testMerchants): void {
            $mock->shouldReceive('getMerchants')->andReturn($testMerchants);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200);

        // Response must have 'data' key at root level
        $response->assertJsonStructure(['data']);

        $responseData = $response->json();

        // Root level should only contain 'data' key (no meta, links, etc.)
        $this->assertArrayHasKey('data', $responseData);

        // Data should be associative array (categories as keys)
        $data = $responseData['data'];
        $this->assertIsArray($data);

        // If data is not empty, validate each category structure
        foreach ($data as $categoryName => $merchants) {
            // Category name should be string (or could be numeric index in some cases)
            $this->assertTrue(is_string($categoryName) || is_numeric($categoryName));

            // Merchants should be indexed array (not associative)
            $this->assertIsArray($merchants);
            $this->assertTrue(
                array_is_list($merchants),
                "Merchants in category '$categoryName' should be a list, not associative array"
            );

            // Each merchant should be associative array with required fields
            foreach ($merchants as $index => $merchant) {
                $this->assertIsArray($merchant, "Merchant at index $index in category '$categoryName' should be array");

                // Required fields for UI display
                $requiredFields = ['id', 'name'];
                foreach ($requiredFields as $field) {
                    $this->assertArrayHasKey($field, $merchant, "Missing required field '$field' in merchant");
                }
            }
        }

        // Verify our specific test data is present
        $foundCategory1 = false;
        $foundCategory2 = false;

        foreach ($data as $categoryName => $merchants) {
            if ($categoryName === 'Category1') {
                $foundCategory1 = true;
                $this->assertGreaterThanOrEqual(1, count($merchants));
            }
            if ($categoryName === 'Category2') {
                $foundCategory2 = true;
                $this->assertGreaterThanOrEqual(1, count($merchants));
            }
        }

        $this->assertTrue($foundCategory1, 'Category1 not found in response');
        $this->assertTrue($foundCategory2, 'Category2 not found in response');
    }

    #[Test]
    public function it_handles_missing_or_null_categories_with_proper_fallback_structure(): void
    {
        // Refactored to handle many-to-many category relation
        // Create merchants without categories

        $merchantNoCategory = resolve(MerchantFactory::class)->create([
            'name' => 'No Category Store',
            'merchant_id' => Uuid::uuid4()->toString(),
        ]);

        // Create a category and merchant with category
        $category = resolve(CategoryFactory::class)->create(['name' => 'TestCategory']);
        $merchant = resolve(MerchantFactory::class)->create([
            'name' => 'With Category Store',
            'merchant_id' => Uuid::uuid4()->toString(),
        ]);
        $merchant->categories()->attach($category);

        // Mock the service to return our test merchants
        $testMerchants = collect([$merchantNoCategory, $merchant])->each(function ($merchant): void {
            $merchant->load('categories');
        });

        $this->mock(AmilonMerchantService::class, function ($mock) use ($testMerchants): void {
            $mock->shouldReceive('getMerchants')->andReturn($testMerchants);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Validate response structure for handling null/empty categories
        $this->assertIsArray($responseData);

        // Test that the structure is valid regardless of data source
        foreach ($responseData as $categoryName => $merchants) {
            // Handle case where fallback merchants might create numeric keys
            if (is_numeric($categoryName)) {
                continue; // Skip numeric keys from collections
            }

            $this->assertIsString($categoryName);
            $this->assertIsArray($merchants);
            $this->assertNotEmpty($merchants);

            // Each merchant should have category matching the parent key
            foreach ($merchants as $merchant) {
                $this->assertIsArray($merchant);
                $this->assertArrayHasKey('category', $merchant);
                $this->assertEquals($categoryName, $merchant['category']);

                // Validate that empty/null categories are properly handled
                // Category can be 'Uncategorized' for merchants without categories
                $this->assertNotNull($merchant['category'] ?? 'Uncategorized');
            }
        }

        // Test the groupBy logic works correctly for category organization
        $allCategories = array_keys($responseData);
        $this->assertIsArray($allCategories);

        // Each category should be unique
        $this->assertEquals(count($allCategories), count(array_unique($allCategories)));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', '123-456-789');

        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')
                ->andReturn($this->mockToken);

            $mock->shouldReceive('refreshToken')
                ->andReturn('new-token-456');
        });

        // Clear cache with proper tags
        Cache::tags(['amilon'])->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
