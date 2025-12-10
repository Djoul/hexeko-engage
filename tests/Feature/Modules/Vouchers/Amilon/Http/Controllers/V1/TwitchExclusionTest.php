<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

/**
 * Test suite to ensure Twitch merchant is properly excluded from all endpoints
 * due to Apple App Store policy restrictions on digital products.
 *
 * UE-655: Exclude Twitch from vouchers (Apple compliance)
 */
#[Group('amilon')]
#[Group('vouchers')]
#[Group('twitch-exclusion')]
#[FlushTables(tables: ['int_vouchers_amilon_products', 'int_vouchers_amilon_merchant_category', 'int_vouchers_amilon_merchants', 'int_vouchers_amilon_categories'], scope: 'class')]
class TwitchExclusionTest extends ProtectedRouteTestCase
{
    private const MERCHANTS_INDEX_URL = '/api/v1/vouchers/amilon/merchants';

    private const MERCHANTS_BY_CATEGORY_URL = '/api/v1/vouchers/amilon/merchants/by-category';

    private const GET_MERCHANTS_AMILON_URL = 'b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/123-456-789/pt-PT/retailers';

    private string $mockToken = 'mock-token-123';

    // Twitch identifiers to test against
    private const TWITCH_ID = '0199bdd3-32b1-72be-905b-591833b488cf';

    private const TWITCH_MERCHANT_ID = 'a4322514-36f1-401e-af3d-6a1784a3da7a';

    protected function setUp(): void
    {
        parent::setUp();

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
    public function it_excludes_twitch_from_merchants_index_by_id(): void
    {
        // Mock API response including Twitch merchant
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'id' => self::TWITCH_ID,
                    'Name' => 'Twitch',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => self::TWITCH_MERCHANT_ID,
                    'LongDescription' => 'Twitch digital gift card',
                    'ImageUrl' => 'https://example.com/twitch.jpg',
                ],
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'ImageUrl' => 'https://example.com/fnac.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Trigger API call to sync merchants
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL);

        $response->assertStatus(200);

        // Verify Twitch is NOT in the response
        $responseData = $response->json();
        foreach ($responseData['data'] as $merchant) {
            $this->assertNotEquals(self::TWITCH_ID, $merchant['id'] ?? null);
            $this->assertNotEquals(self::TWITCH_MERCHANT_ID, $merchant['merchant_id'] ?? null);
            $this->assertStringNotContainsStringIgnoringCase('twitch', $merchant['name'] ?? '');
        }

        // Verify Twitch was NOT saved to database
        $twitchInDb = Merchant::where('id', self::TWITCH_ID)
            ->orWhere('merchant_id', self::TWITCH_MERCHANT_ID)
            ->exists();
        $this->assertFalse($twitchInDb, 'Twitch should not be saved to database');

        // Verify other merchants were saved
        $this->assertTrue(
            Merchant::where('merchant_id', 'FNAC001')->exists(),
            'Other merchants should still be saved'
        );
    }

    #[Test]
    public function it_excludes_twitch_from_merchants_index_by_merchant_id(): void
    {
        // Mock API response with Twitch using only merchant_id (no id field)
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'TwitchTV',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => self::TWITCH_MERCHANT_ID,
                    'LongDescription' => 'Twitch streaming platform',
                    'ImageUrl' => 'https://example.com/twitch.jpg',
                ],
                [
                    'Name' => 'Decathlon',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'DECA001',
                    'LongDescription' => 'Decathlon gift card',
                    'ImageUrl' => 'https://example.com/decathlon.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL);

        $response->assertStatus(200);

        // Verify Twitch is NOT in response
        $responseData = $response->json();
        foreach ($responseData['data'] as $merchant) {
            $this->assertNotEquals(self::TWITCH_MERCHANT_ID, $merchant['merchant_id'] ?? null);
        }

        // Verify Twitch was NOT saved, but Decathlon was
        $this->assertTrue(Merchant::where('merchant_id', 'DECA001')->exists());
        $this->assertFalse(Merchant::where('merchant_id', self::TWITCH_MERCHANT_ID)->exists());
    }

    #[Test]
    public function it_excludes_twitch_from_merchants_index_by_name(): void
    {
        // Mock API response with variations of Twitch name
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'TWITCH',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'DIFFERENT_ID_001',
                    'LongDescription' => 'TWITCH uppercase',
                    'ImageUrl' => 'https://example.com/twitch.jpg',
                ],
                [
                    'Name' => 'twitch',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'DIFFERENT_ID_002',
                    'LongDescription' => 'twitch lowercase',
                    'ImageUrl' => 'https://example.com/twitch2.jpg',
                ],
                [
                    'Name' => 'Twitch Premium',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'DIFFERENT_ID_003',
                    'LongDescription' => 'Twitch with suffix',
                    'ImageUrl' => 'https://example.com/twitch3.jpg',
                ],
                [
                    'Name' => 'Amazon',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'AMZN001',
                    'LongDescription' => 'Amazon gift card',
                    'ImageUrl' => 'https://example.com/amazon.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL);

        $response->assertStatus(200);

        // Verify NO Twitch variants in response
        $responseData = $response->json();
        foreach ($responseData['data'] as $merchant) {
            $this->assertStringNotContainsStringIgnoringCase('twitch', $merchant['name'] ?? '');
        }

        // Verify Amazon was saved, but NO Twitch variants were saved
        $this->assertTrue(Merchant::where('merchant_id', 'AMZN001')->exists());
        $this->assertFalse(Merchant::where('merchant_id', 'DIFFERENT_ID_001')->exists());
        $this->assertFalse(Merchant::where('merchant_id', 'DIFFERENT_ID_002')->exists());
        $this->assertFalse(Merchant::where('merchant_id', 'DIFFERENT_ID_003')->exists());
    }

    #[Test]
    public function it_excludes_twitch_from_merchants_by_category_endpoint(): void
    {
        // Mock API response with Twitch
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'id' => self::TWITCH_ID,
                    'Name' => 'Twitch',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => self::TWITCH_MERCHANT_ID,
                    'LongDescription' => 'Twitch gift card',
                    'ImageUrl' => 'https://example.com/twitch.jpg',
                ],
                [
                    'Name' => 'Netflix',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'NETF001',
                    'LongDescription' => 'Netflix subscription',
                    'ImageUrl' => 'https://example.com/netflix.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_BY_CATEGORY_URL);

        $response->assertStatus(200);

        // Verify Twitch is NOT in any category
        $responseData = $response->json();
        foreach ($responseData['data'] as $merchants) {
            foreach ($merchants as $merchant) {
                $this->assertNotEquals(self::TWITCH_ID, $merchant['id'] ?? null);
                $this->assertNotEquals(self::TWITCH_MERCHANT_ID, $merchant['merchant_id'] ?? null);
                $this->assertStringNotContainsStringIgnoringCase('twitch', $merchant['name'] ?? '');
            }
        }
    }

    #[Test]
    public function it_blocks_direct_access_to_twitch_merchant_by_id(): void
    {
        $user = User::factory()->create();

        // Attempt to access Twitch by its ID
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'/'.self::TWITCH_ID);

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Merchant not found or blocked',
        ]);
    }

    #[Test]
    public function it_blocks_direct_access_to_twitch_merchant_by_merchant_id(): void
    {
        $user = User::factory()->create();

        // Attempt to access Twitch by its merchant_id
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'/'.self::TWITCH_MERCHANT_ID);

        $response->assertStatus(404);
        $response->assertJson([
            'error' => 'Merchant not found or blocked',
        ]);
    }

    #[Test]
    public function it_does_not_break_search_functionality_when_excluding_twitch(): void
    {
        // Mock API response with Twitch and other merchants
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'id' => self::TWITCH_ID,
                    'Name' => 'Twitch',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => self::TWITCH_MERCHANT_ID,
                    'LongDescription' => 'Twitch gift card',
                    'ImageUrl' => 'https://example.com/twitch.jpg',
                ],
                [
                    'Name' => 'Twitter Giftcards',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'TWIT001',
                    'LongDescription' => 'Twitter related merchant (should NOT be excluded)',
                    'ImageUrl' => 'https://example.com/twitter.jpg',
                ],
                [
                    'Name' => 'MediaMarkt',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'MEDI001',
                    'LongDescription' => 'MediaMarkt gift card',
                    'ImageUrl' => 'https://example.com/mediamarkt.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        // Search for "twi" - should NOT return Twitch but should return Twitter
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?search=twi');

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should find Twitter but NOT Twitch
        $merchantNames = array_column($responseData['data'], 'name');
        $this->assertContains('Twitter Giftcards', $merchantNames);
        $this->assertNotContains('Twitch', $merchantNames);
    }

    #[Test]
    public function it_allows_normal_merchants_to_work_when_twitch_is_excluded(): void
    {
        // Mock API with Twitch and normal merchants
        Http::fake([
            'https://b2bsales-sso.amilon.eu/connect/token' => Http::response([
                'access_token' => $this->mockToken,
                'expires_in' => 3600,
            ], 200),
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'id' => self::TWITCH_ID,
                    'Name' => 'Twitch',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => self::TWITCH_MERCHANT_ID,
                    'LongDescription' => 'Twitch (should be excluded)',
                    'ImageUrl' => 'https://example.com/twitch.jpg',
                ],
                [
                    'Name' => 'Spotify',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'SPOT001',
                    'LongDescription' => 'Spotify gift card',
                    'ImageUrl' => 'https://example.com/spotify.jpg',
                ],
                [
                    'Name' => 'Apple',
                    'CountryISOAlpha3' => 'PRT',
                    'RetailerId' => 'APPL001',
                    'LongDescription' => 'Apple gift card',
                    'ImageUrl' => 'https://example.com/apple.jpg',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL);

        $response->assertStatus(200);

        $responseData = $response->json();

        // Should have 2 merchants (Spotify and Apple, but NOT Twitch)
        $this->assertCount(2, $responseData['data']);
        $this->assertEquals(2, $responseData['meta']['total_items']);

        // Verify correct merchants are present
        $merchantNames = array_column($responseData['data'], 'name');
        $this->assertContains('Spotify', $merchantNames);
        $this->assertContains('Apple', $merchantNames);
        $this->assertNotContains('Twitch', $merchantNames);
    }
}
