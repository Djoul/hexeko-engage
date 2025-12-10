<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

/**
 * Test class for Merchant by Category endpoint
 * Tests the grouping of merchants by their categories
 */
#[FlushTables(tables: [
    'int_vouchers_amilon_merchant_category',
    'int_vouchers_amilon_merchants',
    'int_vouchers_amilon_categories'],
    scope: 'class')]
#[Group('amilon')]
#[Group('vouchers')]
class MerchantsByCategoryTest extends ProtectedRouteTestCase
{
    const MERCHANTS_BY_CATEGORY_URL = '/api/v1/vouchers/amilon/merchants/by-category';

    const GET_MERCHANTS_AMILON_URL = 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/123-456-789/PRT/retailers';

    private string $mockToken = 'mock-token-123';

    // Disable auth checks by default (tests will enable when needed)
    protected bool $checkAuth = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up tables in correct order to avoid foreign key violations
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        DB::table('int_vouchers_amilon_products')->delete();
        DB::table('int_vouchers_amilon_merchants')->delete();
        DB::table('int_vouchers_amilon_categories')->delete();

        // Mock Amilon auth service
        Config::set('services.amilon.username', 'test-user');
        Config::set('services.amilon.password', 'test-pass');
        Config::set('services.amilon.auth_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contract_id', '123-456-789');

        // Mock authentication response
        Http::fake([
            'https://b2bsales-api.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        // Clear cache
        Cache::flush();
    }

    #[Test]
    public function it_returns_merchants_organized_by_category(): void
    {
        // Create categories first
        $electronicsCategory = Category::create([
            'id' => fake()->uuid(),
            'name' => 'Electronics',
        ]);

        $sportsCategory = Category::create([
            'id' => fake()->uuid(),
            'name' => 'Sports',
        ]);

        $fashionCategory = Category::create([
            'id' => fake()->uuid(),
            'name' => 'Fashion',
        ]);

        // Create merchants
        $fnac = Merchant::create([
            'id' => fake()->uuid(),
            'name' => 'Fnac',
            'merchant_id' => 'FNAC001',
            'country' => 'FRA',
            'description' => 'Fnac electronics store',
            'image_url' => 'https://example.com/fnac.jpg',
        ]);

        $decathlon = Merchant::create([
            'id' => fake()->uuid(),
            'name' => 'Decathlon',
            'merchant_id' => 'DECA001',
            'country' => 'FRA',
            'description' => 'Decathlon sports store',
            'image_url' => 'https://example.com/decathlon.jpg',
        ]);

        $zara = Merchant::create([
            'id' => fake()->uuid(),
            'name' => 'Zara',
            'merchant_id' => 'ZARA001',
            'country' => 'ESP',
            'description' => 'Zara fashion store',
            'image_url' => 'https://example.com/zara.jpg',
        ]);

        $mediamarkt = Merchant::create([
            'id' => fake()->uuid(),
            'name' => 'MediaMarkt',
            'merchant_id' => 'MEMA001',
            'country' => 'DEU',
            'description' => 'MediaMarkt electronics store',
            'image_url' => 'https://example.com/mediamarkt.jpg',
        ]);

        // Attach categories to merchants
        $fnac->categories()->attach($electronicsCategory);
        $decathlon->categories()->attach($sportsCategory);
        $zara->categories()->attach($fashionCategory);
        $mediamarkt->categories()->attach($electronicsCategory);

        // Mock API response (even though we're using DB data)
        Http::fake([
            self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'FRA',
                    'RetailerId' => 'FNAC001',
                    'LongDescription' => 'Fnac electronics store',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                ],
                [
                    'Name' => 'Decathlon',
                    'CountryISOAlpha3' => 'FRA',
                    'RetailerId' => 'DECA001',
                    'LongDescription' => 'Decathlon sports store',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                ],
                [
                    'Name' => 'Zara',
                    'CountryISOAlpha3' => 'ESP',
                    'RetailerId' => 'ZARA001',
                    'LongDescription' => 'Zara fashion store',
                    'ImageUrl' => 'https://example.com/zara.jpg',
                ],
                [
                    'Name' => 'MediaMarkt',
                    'CountryISOAlpha3' => 'DEU',
                    'RetailerId' => 'MEMA001',
                    'LongDescription' => 'MediaMarkt electronics store',
                    'ImageUrl' => 'https://example.com/mediamarkt.jpg',
                ],
            ], 200),
        ]);

        // Create user with ModelFactory
        $user = $this->createAuthUser();

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
                            'merchant_id',
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
                            'merchant_id',
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
                            'merchant_id',
                            'description',
                            'image_url',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
            ]);

        $responseData = $response->json('data');

        $this->assertArrayHasKey('Electronics', $responseData);
        $this->assertArrayHasKey('Sports', $responseData);
        $this->assertArrayHasKey('Fashion', $responseData);

        $this->assertCount(2, $responseData['Electronics']);
        $this->assertCount(1, $responseData['Sports']);
        $this->assertCount(1, $responseData['Fashion']);

        // Check merchant names are correct
        $electronicsNames = array_column($responseData['Electronics'], 'name');
        $this->assertContains('Fnac', $electronicsNames);
        $this->assertContains('MediaMarkt', $electronicsNames);
        $this->assertEquals('Decathlon', $responseData['Sports'][0]['name']);
        $this->assertEquals('Zara', $responseData['Fashion'][0]['name']);
    }

    #[Test]
    public function it_returns_empty_categories_when_no_merchants_exist(): void
    {
        Http::fake([
            self::GET_MERCHANTS_AMILON_URL => Http::response([], 200),
        ]);

        $user = $this->createAuthUser();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
            ]);
    }

    #[Test]
    public function it_groups_merchants_without_category_as_uncategorized(): void
    {
        // Clean up tables to ensure clean environment
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        DB::table('int_vouchers_amilon_products')->delete();
        DB::table('int_vouchers_amilon_merchants')->delete();
        DB::table('int_vouchers_amilon_categories')->delete();

        // Clear cache
        Cache::flush();

        // Create a merchant without category
        Merchant::create([
            'id' => fake()->uuid(),
            'name' => 'Generic Store',
            'merchant_id' => 'GENE001',
            'country' => 'FRA',
            'description' => 'Generic store without category',
            'image_url' => 'https://example.com/generic.jpg',
        ]);

        // Mock API response
        Http::fake([
            self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Generic Store',
                    'CountryISOAlpha3' => 'FRA',
                    'RetailerId' => 'GENE001',
                    'LongDescription' => 'Generic store without category',
                    'ImageUrl' => 'https://example.com/generic.jpg',
                ],
            ], 200),
        ]);

        $user = $this->createAuthUser();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200);

        $responseData = $response->json('data');

        $this->assertArrayHasKey('Uncategorized', $responseData);
        $this->assertCount(1, $responseData['Uncategorized']);
        $this->assertEquals('Generic Store', $responseData['Uncategorized'][0]['name']);
    }
}
