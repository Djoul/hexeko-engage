<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class MerchantFilteringTest extends ProtectedRouteTestCase
{
    const MERCHANTS_INDEX_URL = '/api/v1/vouchers/amilon/merchants';

    const GET_MERCHANTS_AMILON_URL = 'b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/123-456-789/pt-PT/retailers';

    private string $mockToken = 'mock-token-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Amilon tables to avoid conflicts (in correct order for foreign keys)
        DB::table('int_vouchers_amilon_products')->delete();
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        DB::table('int_vouchers_amilon_merchants')->delete();
        DB::table('int_vouchers_amilon_categories')->delete();

        // Run Amilon migrations
        $this->artisan('migrate', [
            '--path' => 'app/Integrations/Vouchers/Amilon/Database/migrations',
            '--realpath' => false,
        ]);

        // Set up config for tests
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', '123-456-789');
        Config::set('services.amilon.token_url', 'https://b2bsales-sso.amilon.eu/connect/token');
        Config::set('services.amilon.client_id', 'test-client');
        Config::set('services.amilon.client_secret', 'test-secret');

        // Mock the auth service
        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')
                ->andReturn($this->mockToken);
            $mock->shouldReceive('refreshToken')
                ->andReturn('new-token-456');
        });

    }

    #[Test]
    public function test_merchants_endpoint_with_category_filter(): void
    {
        // Create categories
        $electronicsCategory = Category::create(['name' => 'Electronics']);
        $sportsCategory = Category::create(['name' => 'Sports']);

        // Mock HTTP response with multiple merchants
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response(['access_token' => $this->mockToken, 'expires_in' => 3600], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => 'FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                ],
                [
                    'Name' => 'Decathlon',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => 'DECA001',
                    'LongDescription' => 'Decathlon gift card',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                ],
                [
                    'Name' => 'MediaMarkt',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => 'MEDI001',
                    'LongDescription' => 'MediaMarkt gift card',
                    'ImageUrl' => 'https://example.com/mediamarkt.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Trigger the API call to create merchants
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL);
        $response->assertStatus(200);

        // Check if merchants were created
        $merchantCount = Merchant::count();
        $this->assertGreaterThan(0, $merchantCount, 'No merchants were created from API response');

        // Manually associate categories with merchants
        $fnac = Merchant::where('merchant_id', 'FNAC001')->first();
        $this->assertNotNull($fnac, 'Fnac merchant not found');
        $fnac->categories()->attach($electronicsCategory);

        $decathlon = Merchant::where('merchant_id', 'DECA001')->first();
        $this->assertNotNull($decathlon, 'Decathlon merchant not found');
        $decathlon->categories()->attach($sportsCategory);

        $mediamarkt = Merchant::where('merchant_id', 'MEDI001')->first();
        $this->assertNotNull($mediamarkt, 'MediaMarkt merchant not found');
        $mediamarkt->categories()->attach($electronicsCategory);

        // Test filtering by Electronics category
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?category=Electronics');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']); // Should have 2 electronics merchants
        $this->assertEquals(2, $responseData['meta']['total_items']);

        // Verify all returned merchants are in Electronics category
        foreach ($responseData['data'] as $merchant) {
            $this->assertEquals('Electronics', $merchant['category']);
        }

        // Test filtering by Sports category
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?category=Sports');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']); // Should have 1 sports merchant
        $this->assertEquals(1, $responseData['meta']['total_items']);
        $this->assertEquals('Sports', $responseData['data'][0]['category']);

        // Test filtering by non-existing category
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?category=NonExisting');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(0, $responseData['data']); // Should have no merchants
        $this->assertEquals(0, $responseData['meta']['total_items']);
    }

    #[Test]
    public function test_merchants_endpoint_with_search_query(): void
    {

        // Generate unique merchant IDs for this test
        $testId = uniqid('SRCH_');

        // Mock HTTP response with merchants having different names
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response(['access_token' => $this->mockToken, 'expires_in' => 3600], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                ],
                [
                    'Name' => 'Fnac Darty',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_FNACD001',
                    'LongDescription' => 'Fnac Darty gift card',
                    'ImageUrl' => 'https://example.com/fnac-darty.jpg',
                ],
                [
                    'Name' => 'Decathlon',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_DECA001',
                    'LongDescription' => 'Decathlon gift card',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Trigger API to create merchants
        $response = $this->actingAs($user)->getJson(self::MERCHANTS_INDEX_URL);
        $response->assertStatus(200);

        // Don't clear cache - use data directly from DB which was just created

        // Test search by exact name
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?search=Fnac');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(2, $responseData['data']); // Should match 'Fnac' and 'Fnac Darty'
        $this->assertEquals(2, $responseData['meta']['total_items']);

        // Verify all returned merchants contain 'Fnac' in name
        foreach ($responseData['data'] as $merchant) {
            $this->assertStringContainsStringIgnoringCase('Fnac', $merchant['name']);
        }

        // Test search by partial name (case insensitive)
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?search=deca');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']); // Should match 'Decathlon'
        $this->assertEquals(1, $responseData['meta']['total_items']);
        $this->assertStringContainsStringIgnoringCase('Decathlon', $responseData['data'][0]['name']);

        // Test search with no matches
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?search=NonExistingMerchant');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(0, $responseData['data']); // Should have no matches
        $this->assertEquals(0, $responseData['meta']['total_items']);
    }

    #[Test]
    public function test_merchants_endpoint_with_multiple_filters(): void
    {

        // Generate unique test ID for this test
        $testId = uniqid('MULT_');

        // Create categories
        $electronicsCategory = Category::create(['name' => 'Electronics']);
        $sportsCategory = Category::create(['name' => 'Sports']);

        // Mock HTTP response with diverse merchants
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response(['access_token' => $this->mockToken, 'expires_in' => 3600], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                ],
                [
                    'Name' => 'MediaMarkt',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_MEDI001',
                    'LongDescription' => 'MediaMarkt gift card',
                    'ImageUrl' => 'https://example.com/mediamarkt.jpg',
                ],
                [
                    'Name' => 'Decathlon',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_DECA001',
                    'LongDescription' => 'Decathlon gift card',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                ],
                [
                    'Name' => 'Fnac Sports',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_FNACS001',
                    'LongDescription' => 'Fnac Sports gift card',
                    'ImageUrl' => 'https://example.com/fnac-sports.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Trigger API to create merchants
        $response = $this->actingAs($user)->getJson(self::MERCHANTS_INDEX_URL);
        $response->assertStatus(200);

        // Wait for merchants to be saved to database (parallel test issue)
        $maxAttempts = 10;
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $merchantCount = Merchant::whereIn('merchant_id', [
                $testId.'_FNAC001',
                $testId.'_MEDI001',
                $testId.'_DECA001',
                $testId.'_FNACS001',
            ])->count();
            if ($merchantCount === 4) {
                break;
            }
            usleep(100000); // Wait 100ms
            $attempts++;
        }

        // Associate categories with merchants
        $fnac = Merchant::where('merchant_id', $testId.'_FNAC001')->first();
        $this->assertNotNull($fnac, 'Merchant FNAC001 not found in database');
        $fnac->categories()->attach($electronicsCategory);

        $mediamarkt = Merchant::where('merchant_id', $testId.'_MEDI001')->first();
        $this->assertNotNull($mediamarkt, 'Merchant MEDI001 not found in database');
        $mediamarkt->categories()->attach($electronicsCategory);

        $decathlon = Merchant::where('merchant_id', $testId.'_DECA001')->first();
        $this->assertNotNull($decathlon, 'Merchant DECA001 not found in database');
        $decathlon->categories()->attach($sportsCategory);

        $fnacSports = Merchant::where('merchant_id', $testId.'_FNACS001')->first();
        $this->assertNotNull($fnacSports, 'Merchant FNACS001 not found in database');
        $fnacSports->categories()->attach($sportsCategory);

        // Test combining category filter and search
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?category=Electronics&search=Fnac');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']); // Should match only 'Fnac' in Electronics
        $this->assertEquals(1, $responseData['meta']['total_items']);
        $this->assertEquals('Fnac', $responseData['data'][0]['name']);
        $this->assertEquals('Electronics', $responseData['data'][0]['category']);

        // Test combining all filters with pagination and sorting
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?category=Electronics&search=M&sort=name&order=asc&page=1&per_page=10');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertCount(1, $responseData['data']); // Should match only 'MediaMarkt'
        $this->assertEquals(1, $responseData['meta']['total_items']);
        $this->assertEquals('MediaMarkt', $responseData['data'][0]['name']);
        $this->assertEquals('Electronics', $responseData['data'][0]['category']);
    }

    #[Test]
    public function test_merchants_endpoint_sorting_options(): void
    {

        DB::table('int_vouchers_amilon_merchants')->delete();

        // Generate unique test ID for this test
        $testId = uniqid('SORT_');

        // Create categories
        $fashionCategory = Category::create(['name' => 'Fashion']);
        $onlineCategory = Category::create(['name' => 'Online']);
        $electronicsCategory = Category::create(['name' => 'Electronics']);

        // Mock HTTP response with merchants in different order
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response(['access_token' => $this->mockToken, 'expires_in' => 3600], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Zara',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_ZARA001',
                    'LongDescription' => 'Zara gift card',
                    'ImageUrl' => 'https://example.com/zara.jpg',
                ],
                [
                    'Name' => 'Amazon',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_AMZN001',
                    'LongDescription' => 'Amazon gift card',
                    'ImageUrl' => 'https://example.com/amazon.jpg',
                ],
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'ITA',
                    'RetailerId' => $testId.'_FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Trigger API to create merchants
        $response = $this->actingAs($user)->getJson(self::MERCHANTS_INDEX_URL);
        $response->assertStatus(200);

        // Wait for merchants to be saved
        $maxAttempts = 10;
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $merchantCount = Merchant::whereIn('merchant_id', [
                $testId.'_ZARA001',
                $testId.'_AMZN001',
                $testId.'_FNAC001',
            ])->count();
            if ($merchantCount === 3) {
                break;
            }
            usleep(100000); // Wait 100ms
            $attempts++;
        }

        // Associate categories
        $zara = Merchant::where('merchant_id', $testId.'_ZARA001')->first();
        $this->assertNotNull($zara, 'Merchant ZARA001 not found');
        $zara->categories()->attach($fashionCategory);

        $amazon = Merchant::where('merchant_id', $testId.'_AMZN001')->first();
        $this->assertNotNull($amazon, 'Merchant AMZN001 not found');
        $amazon->categories()->attach($onlineCategory);

        $fnac = Merchant::where('merchant_id', $testId.'_FNAC001')->first();
        $this->assertNotNull($fnac, 'Merchant FNAC001 not found');
        $fnac->categories()->attach($electronicsCategory);

        // Test sort by name ascending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=name&order=asc');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('Amazon', $responseData['data'][0]['name']);
        $this->assertEquals('Fnac', $responseData['data'][1]['name']);
        $this->assertEquals('Zara', $responseData['data'][2]['name']);

        // Test sort by name descending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=name&order=desc');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('Zara', $responseData['data'][0]['name']);
        $this->assertEquals('Fnac', $responseData['data'][1]['name']);
        $this->assertEquals('Amazon', $responseData['data'][2]['name']);

        // Test sort by category ascending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=category&order=asc');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('Electronics', $responseData['data'][0]['category']);
        $this->assertEquals('Fashion', $responseData['data'][1]['category']);
        $this->assertEquals('Online', $responseData['data'][2]['category']);

        // Test sort by category descending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=category&order=desc');

        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals('Online', $responseData['data'][0]['category']);
        $this->assertEquals('Fashion', $responseData['data'][1]['category']);
        $this->assertEquals('Electronics', $responseData['data'][2]['category']);
    }
}
