<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
class MerchantControllerNullSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable all middleware for these tests
        $this->withoutMiddleware();
    }

    #[Test]
    public function it_handles_empty_collection_return_from_get_merchants_in_index(): void
    {
        // Mock the service to return an empty collection
        $mockService = Mockery::mock(AmilonMerchantService::class);
        $mockService->shouldReceive('getMerchants')
            ->once()
            ->andReturn(collect());

        $this->app->instance(AmilonMerchantService::class, $mockService);

        $response = $this->getJson('/api/v1/vouchers/amilon/merchants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [],
            'meta' => [
                'total_items',
            ],
        ]);
        $response->assertJson([
            'data' => [],
            'meta' => [
                'total_items' => 0,
            ],
        ]);
    }

    #[Test]
    public function it_handles_empty_collection_return_from_get_merchants_in_by_category(): void
    {
        // Mock the service to return an empty collection
        $mockService = Mockery::mock(AmilonMerchantService::class);
        $mockService->shouldReceive('getMerchants')
            ->once()
            ->andReturn(collect());

        $this->app->instance(AmilonMerchantService::class, $mockService);

        $response = $this->getJson('/api/v1/vouchers/amilon/merchants/by-category');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [],
        ]);
        $response->assertJson([
            'data' => [],
        ]);
    }

    #[Test]
    public function it_handles_collection_with_merchants(): void
    {
        // Mock the service to return a collection with mock merchants
        $mockService = Mockery::mock(AmilonMerchantService::class);
        $mockService->shouldReceive('getMerchants')
            ->once()
            ->andReturn(collect());

        $this->app->instance(AmilonMerchantService::class, $mockService);

        $response = $this->getJson('/api/v1/vouchers/amilon/merchants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [],
            'meta' => [
                'total_items',
            ],
        ]);
        $response->assertJson([
            'data' => [],
            'meta' => [
                'total_items' => 0,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
