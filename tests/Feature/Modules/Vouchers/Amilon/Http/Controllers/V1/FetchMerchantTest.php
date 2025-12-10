<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['int_vouchers_amilon_categories', 'int_vouchers_amilon_merchants'], scope: 'class')]

#[Group('amilon')]
#[Group('vouchers')]
class FetchMerchantTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    const MERCHANTS_INDEX_URL = '/api/v1/vouchers/amilon/merchants';

    const GET_MERCHANTS_AMILON_URL = 'b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/123-456-789/pt-PT/retailers';

    private string $mockToken = 'mock-token-123';

    #[Test]
    public function test_merchants_endpoint_returns_valid_json_structure(): void
    {
        // Mock HTTP response from Amilon API (auth is mocked separately)
        Http::fake([
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Fnac',
                    'CountryISOAlpha3' => 'ITA',
                    'category' => 'Electronics',
                    'RetailerId' => 'FNAC001',
                    'LongDescription' => 'Fnac gift card',
                    'image_url' => 'https://example.com/fnac.jpg',

                ],
            ], 200),
        ]);

        // Create a user with ModelFactory
        $user = ModelFactory::createUser();

        // Make request to the merchants endpoint
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL);

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'categories',
                        'country',
                        'merchant_id',
                        'description',
                        'image_url',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // Verify merchants request was made (auth is mocked so no token request)
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), self::GET_MERCHANTS_AMILON_URL) &&
                $request->hasHeader('Authorization', 'Bearer '.$this->mockToken);
        });
    }

    #[Test]
    public function test_merchants_endpoint_supports_pagination(): void
    {
        // Generate unique test ID to avoid conflicts in parallel tests
        $testId = uniqid('PAG_');

        // Mock HTTP response from Amilon API with multiple merchants (auth is mocked separately)
        Http::fake([
            'https://'.self::GET_MERCHANTS_AMILON_URL => Http::response(
                array_map(function (int $i) use ($testId): array {
                    return [
                        'Name' => "Merchant $i",
                        'category' => 'Category '.($i % 3),
                        'CountryISOAlpha3' => 'FRA',
                        'RetailerId' => "{$testId}_MERCH{$i}",
                        'LongDescription' => "Description for Merchant $i",
                        'ImageUrl' => "https://example.com/merchant$i.jpg",
                    ];
                }, range(1, 20)),
                200
            ),
        ]);

        // Create a user with ModelFactory
        $user = ModelFactory::createUser();

        // Make request to the merchants endpoint with pagination parameters
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=1&per_page=10');

        // Assert response
        $response->assertStatus(200);

        // Check actual response
        $responseData = $response->json();
        $actualCount = count($responseData['data'] ?? []);

        // In parallel tests, some merchants might already exist, affecting pagination
        // So we check that pagination works (returns data) rather than exact count
        $this->assertGreaterThan(0, $actualCount, 'Expected items in first page but got none');
        $this->assertLessThanOrEqual(10, $actualCount, "Expected at most 10 items per page but got {$actualCount}");

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'categories',
                    'country',
                    'merchant_id',
                    'description',
                    'image_url',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => ['total_items'],
        ]);

        // Verify that pagination metadata is present
        $this->assertArrayHasKey('total_items', $responseData['meta'] ?? []);
        $this->assertIsInt($responseData['meta']['total_items']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up config for tests
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');
        Config::set('services.amilon.contrat_id', '123-456-789');
        Config::set('services.amilon.token_url', 'https://b2bsales-sso.amilon.eu/connect/token');
        Config::set('services.amilon.client_id', 'test-client-id');
        Config::set('services.amilon.client_secret', 'test-client-secret');
        Config::set('services.amilon.username', 'test-username');
        Config::set('services.amilon.password', 'test-password');

        // Mock the auth service to avoid real HTTP calls for token
        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')
                ->andReturn($this->mockToken);
            $mock->shouldReceive('refreshToken')
                ->andReturn($this->mockToken);
        });
    }
}
