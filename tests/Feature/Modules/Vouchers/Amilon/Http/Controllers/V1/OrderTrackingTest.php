<?php

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonAuthService;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]

class OrderTrackingTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected User $user;

    protected Order $order;

    protected string $externalOrderId;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the auth service to return a fake token
        $this->mock(AmilonAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')->andReturn('fake-token');
            $mock->shouldReceive('refreshToken')->andReturn('refreshed-fake-token');
        });

        // Create a test user
        $this->user = ModelFactory::createUser();

        // Create a test product
        $product = Product::factory()->create([
            'product_code' => 'TEST-PRODUCT-123',
            'name' => 'Test Product',
            'price' => 50.0,
        ]);

        // Create a test order
        $this->externalOrderId = Uuid::uuid4()->toString();
        $this->order = Order::factory()->create([
            'external_order_id' => $this->externalOrderId,
            'user_id' => $this->user->id,
            'merchant_id' => $product->merchant_id,
            'amount' => 50.0,
            'status' => 'pending',
            'order_status' => 'pending',
        ]);
    }

    #[Test]
    public function test_get_order_info_returns_expected_status(): void
    {

        $this->withoutExceptionHandling();
        // Mock the HTTP client to return a successful response with a specific status
        Http::fake([
            '*/Orders/*' => Http::response([
                'orderStatus' => 'confirmed',
                'externalOrderId' => $this->externalOrderId,
            ], 200),
        ]);

        // Act: Call the endpoint to get order info
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/vouchers/amilon/orders/{$this->externalOrderId}");
        // Assert: Response is successful and contains the expected status
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.external_order_id', $this->externalOrderId);

        // Assert: Order status was updated in the database
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'external_order_id' => $this->externalOrderId,
            'status' => 'confirmed',
            'order_status' => 'confirmed',
        ]);
    }

    #[Test]
    public function test_timeout_triggers_get_order_info(): void
    {
        // Mock the HTTP client to first timeout, then return a successful response
        Http::fake([
            '*/Orders/*' => Http::sequence()
                ->push('', 408) // First request times out
                ->push(['orderStatus' => 'confirmed', 'externalOrderId' => $this->externalOrderId], 200), // Second request succeeds
        ]);

        // Act: Call the endpoint to get order info
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/vouchers/amilon/orders/{$this->externalOrderId}");

        // Assert: Response falls back to database data
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending') // Original status from database
            ->assertJsonPath('data.external_order_id', $this->externalOrderId);

        // Make a second request which should succeed
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/vouchers/amilon/orders/{$this->externalOrderId}");

        // Assert: Second response has updated status
        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.external_order_id', $this->externalOrderId);

        // Assert: Order status was updated in the database
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'external_order_id' => $this->externalOrderId,
            'status' => 'confirmed',
            'order_status' => 'confirmed',
        ]);
    }

    #[Test]
    public function test_order_not_found_returns_404(): void
    {
        // Act: Call the endpoint with a non-existent order ID (valid UUID format)
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/vouchers/amilon/orders/{$nonExistentUuid}");

        // Assert: Response is a 404 not found
        $response->assertStatus(404)
            ->assertJsonPath('error', 'Not found')
            ->assertJsonPath('message', 'Order not found');
    }

    #[Test]
    public function test_unauthorized_user_cannot_view_order(): void
    {
        // Create another user
        $anotherUser = ModelFactory::createUser();

        // Act: Call the endpoint as a different user
        $response = $this->actingAs($anotherUser)
            ->getJson("/api/v1/vouchers/amilon/orders/{$this->externalOrderId}");

        // Assert: Response is a 403 forbidden
        $response->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden')
            ->assertJsonPath('message', 'You do not have permission to view this order');
    }
}
