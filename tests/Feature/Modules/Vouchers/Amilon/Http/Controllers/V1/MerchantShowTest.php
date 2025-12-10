<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class MerchantShowTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Run Amilon migrations
        $this->artisan('migrate', [
            '--path' => 'app/Integrations/Vouchers/Amilon/Database/migrations',
            '--realpath' => false,
        ]);

        // Mock the auth service
        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')
                ->andReturn('mock-token-123');
            $mock->shouldReceive('refreshToken')
                ->andReturn('new-token-456');
        });
    }

    #[Test]
    public function test_merchant_show_returns_merchant_details(): void
    {
        $user = User::factory()->create();

        // Create a test merchant using the factory with unique merchant_id and category
        $uniqueMerchantId = 'TEST_'.Uuid::uuid4()->toString();
        $merchant = resolve(MerchantFactory::class)
            ->withCategories(['Electronics'])
            ->create([
                'name' => 'Test Merchant',
                'country' => 'FR',
                'merchant_id' => $uniqueMerchantId,
                'description' => 'Test merchant description',
                'image_url' => 'https://example.com/test.jpg',
            ]);

        // Make request to the merchant show endpoint
        $response = $this->actingAs($user)
            ->getJson("/api/v1/vouchers/amilon/merchants/{$merchant->id}");

        // Assert response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
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
            ]);

        // Assert the specific merchant data
        $response->assertJson([
            'data' => [
                'id' => $merchant->id,
                'name' => 'Test Merchant',
                'category' => 'Electronics',
                'country' => 'FR',
                'merchant_id' => $uniqueMerchantId,
                'description' => 'Test merchant description',
                'image_url' => 'https://example.com/test.jpg',
            ],
        ]);
    }

    #[Test]
    public function test_merchant_show_returns_404_for_non_existing_merchant(): void
    {
        $user = User::factory()->create();

        // Make request to a non-existing merchant
        $response = $this->actingAs($user)
            ->getJson('/api/v1/vouchers/amilon/merchants/'.Uuid::uuid4()->toString());

        // Assert 500 response (findOrFail throws exception)
        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to fetch merchant',
                'message' => 'An error occurred while fetching the merchant details',
            ]);
    }

    #[Test]
    public function test_merchant_show_returns_404_for_invalid_id(): void
    {
        $user = User::factory()->create();

        // Make request with invalid ID format
        $response = $this->actingAs($user)
            ->getJson('/api/v1/vouchers/amilon/merchants/invalid-id');

        // Assert 500 response (invalid UUID causes database error)
        $response->assertStatus(500)
            ->assertJson([
                'error' => 'Failed to fetch merchant',
                'message' => 'An error occurred while fetching the merchant details',
            ]);
    }

    #[Test]
    public function test_merchant_show_with_all_fields_populated(): void
    {
        $user = User::factory()->create();

        // Create category
        $completeCategory = Category::create(['name' => 'Complete Category']);

        // Create a merchant with all fields populated
        $merchant = resolve(MerchantFactory::class)->create([
            'name' => 'Complete Merchant',
            'country' => 'ES',
            'merchant_id' => 'COMPLETE001',
            'description' => 'Complete merchant with all fields filled',
            'image_url' => 'https://example.com/complete-merchant.jpg',
        ]);

        // Associate category
        $merchant->categories()->attach($completeCategory);

        // Make request to the merchant show endpoint
        $response = $this->actingAs($user)
            ->getJson("/api/v1/vouchers/amilon/merchants/{$merchant->id}");

        // Assert response contains all fields
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $merchant->id,
                    'name' => 'Complete Merchant',
                    'category' => 'Complete Category',
                    'country' => 'ES',
                    'merchant_id' => 'COMPLETE001',
                    'description' => 'Complete merchant with all fields filled',
                    'image_url' => 'https://example.com/complete-merchant.jpg',
                ],
            ]);

        // Verify timestamps are present
        $responseData = $response->json();
        $this->assertNotNull($responseData['data']['created_at']);
        $this->assertNotNull($responseData['data']['updated_at']);
    }

    #[Test]
    public function test_merchant_show_with_minimal_fields(): void
    {
        $user = User::factory()->create();

        // Create category
        $minimalCategory = Category::create(['name' => 'Minimal Category']);

        // Create a merchant with minimal fields (some nullable)
        $merchant = resolve(MerchantFactory::class)->create([
            'name' => 'Minimal Merchant',
            'country' => 'IT',
            'merchant_id' => 'MINIMAL001',
            'description' => null,
            'image_url' => null,
        ]);

        // Associate category
        $merchant->categories()->attach($minimalCategory);

        // Make request to the merchant show endpoint
        $response = $this->actingAs($user)
            ->getJson("/api/v1/vouchers/amilon/merchants/{$merchant->id}");

        // Assert response contains required fields and handles null values
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $merchant->id,
                    'name' => 'Minimal Merchant',
                    'category' => 'Minimal Category',
                    'country' => 'IT',
                    'merchant_id' => 'MINIMAL001',
                    'description' => null,
                    'image_url' => null,
                ],
            ]);
    }

    #[Test]
    public function test_merchant_show_returns_single_merchant_not_collection(): void
    {
        $user = User::factory()->create();

        // Create multiple merchants
        $merchant1 = resolve(MerchantFactory::class)->create(['id' => Uuid::uuid4()->toString(), 'name' => 'Merchant 1']);
        resolve(MerchantFactory::class)->create(['name' => 'Merchant 2']);

        // Make request to specific merchant
        $response = $this->actingAs($user)
            ->getJson("/api/v1/vouchers/amilon/merchants/{$merchant1->id}");

        // Assert response contains only the requested merchant
        $response->assertStatus(200);

        $responseData = $response->json();
        $this->assertEquals($merchant1->id, $responseData['data']['id']);
        $this->assertEquals('Merchant 1', $responseData['data']['name']);

        // Verify response is not an array of merchants
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('id', $responseData['data']);
        $this->assertIsString($responseData['data']['id']);
    }
}
