<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class MerchantValidationTest extends ProtectedRouteTestCase
{
    const MERCHANTS_INDEX_URL = '/api/v1/vouchers/amilon/merchants';

    const GET_MERCHANTS_AMILON_URL = 'b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/123-456-789/PRT/retailers';

    private string $mockToken = 'mock-token-123';

    protected function setUp(): void
    {
        parent::setUp();
        // Set up config for tests
        Config::set('services.amilon.api_url', 'https://b2bsales-api.amilon.eu');

        // Mock the auth service
        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')
                ->andReturn($this->mockToken);

            $mock->shouldReceive('refreshToken')
                ->andReturn('new-token-456');
        });

        // Mock basic successful HTTP response
        Http::fake([
            self::GET_MERCHANTS_AMILON_URL => Http::response([
                [
                    'Name' => 'Test Merchant',
                    'CountryISOAlpha3' => 'ITA',
                    'category' => 'Electronics',
                    'RetailerId' => 'TEST001',
                    'LongDescription' => 'Test merchant',
                    'ImageUrl' => 'https://example.com/test.jpg',
                ],
            ], 200),
        ]);
    }

    #[Test]
    public function test_merchants_endpoint_with_invalid_category_returns_400(): void
    {
        $user = User::factory()->create();

        // Test with non-string category
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?category[]=invalid');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid category parameter',
                'message' => 'category must be a string',
            ]);
    }

    #[Test]
    public function test_merchants_endpoint_with_invalid_pagination_returns_400(): void
    {
        $user = User::factory()->create();

        // Test with invalid per_page (too large)
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?per_page=150');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'per_page must be between 1 and 100',
            ]);

        // Test with invalid per_page (zero)
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?per_page=0');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'per_page must be between 1 and 100',
            ]);

        // Test with invalid per_page (negative)
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?per_page=-5');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'per_page must be between 1 and 100',
            ]);

        // Test with invalid page (zero)
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=0');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'page must be greater than 0',
            ]);

        // Test with invalid page (negative)
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=-1');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'page must be greater than 0',
            ]);

        // Test with non-numeric pagination values
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?per_page=abc');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'per_page must be between 1 and 100',
            ]);

        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=abc');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid pagination parameters',
                'message' => 'page must be greater than 0',
            ]);
    }

    #[Test]
    public function test_merchants_endpoint_with_invalid_sort_parameter_returns_400(): void
    {
        $user = User::factory()->create();

        // Test with invalid sort field
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=invalid_field');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid sort parameter',
                'message' => 'sort must be one of: name, category',
            ]);

        // Test with invalid order
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?order=invalid_order');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'Invalid order parameter',
                'message' => 'order must be one of: asc, desc',
            ]);
    }

    #[Test]
    public function test_merchants_endpoint_with_valid_pagination(): void
    {
        $user = User::factory()->create();

        // Test with valid pagination parameters
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
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
                'meta' => [
                    'total_items',
                    'current_page',
                    'per_page',
                    'last_page',
                ],
            ]);

        // Verify pagination metadata
        $response->assertJson([
            'meta' => [
                'current_page' => 1,
                'per_page' => 10,
            ],
        ]);
    }

    #[Test]
    public function test_merchants_endpoint_with_valid_sort_parameters(): void
    {
        $user = User::factory()->create();

        // Test with valid sort by name ascending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=name&order=asc');

        $response->assertStatus(200);

        // Test with valid sort by name descending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=name&order=desc');

        $response->assertStatus(200);

        // Test with valid sort by category ascending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=category&order=asc');

        $response->assertStatus(200);

        // Test with valid sort by category descending
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?sort=category&order=desc');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_merchants_endpoint_with_boundary_pagination_values(): void
    {
        $user = User::factory()->create();

        // Test minimum valid values
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=1&per_page=1');

        $response->assertStatus(200);

        // Test maximum valid per_page
        $response = $this->actingAs($user)
            ->getJson(self::MERCHANTS_INDEX_URL.'?page=1&per_page=100');

        $response->assertStatus(200);
    }
}
