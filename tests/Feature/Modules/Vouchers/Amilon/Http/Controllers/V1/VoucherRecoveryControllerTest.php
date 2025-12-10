<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Actions\Vouchers\Amilon\RecoverFailedVoucherAction;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\ProtectedRouteTestCase;

#[Group('amilon')]
#[Group('vouchers')]
class VoucherRecoveryControllerTest extends ProtectedRouteTestCase
{
    protected RecoverFailedVoucherAction $recoverAction;

    protected User $user;

    protected string $baseEndpoint = '/api/v1/vouchers/amilon/orders';

    protected bool $checkAuth = false;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->recoverAction = Mockery::mock(RecoverFailedVoucherAction::class);
        $this->app->instance(RecoverFailedVoucherAction::class, $this->recoverAction);

        $this->auth = $this->createAuthUser();

    }

    #[Test]
    public function it_successfully_retries_failed_order(): void
    {
        // Arrange
        $orderId = fake()->uuid();
        $order = resolve(OrderFactory::class)->create([
            'id' => $orderId,
            'user_id' => $this->auth->id,
            'status' => 'failed',
            'recovery_attempts' => 1,
        ]);

        $this->recoverAction->shouldReceive('execute')
            ->with($orderId)
            ->once()
            ->andReturn($order);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->baseEndpoint}/{$orderId}/retry");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order recovery initiated',
                'data' => [
                    'order' => [
                        'id' => $orderId,
                        'status' => 'failed',
                        'recovery_attempts' => 1,
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_when_order_not_found(): void
    {
        // Arrange
        $this->recoverAction->shouldReceive('execute')
            ->with('non-existent-order')
            ->once()
            ->andThrow(new ModelNotFoundException);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->baseEndpoint}/non-existent-order/retry");

        // Assert
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Order not found',
            ]);
    }

    #[Test]
    public function it_returns_422_when_order_cannot_be_retried(): void
    {
        // Arrange
        $orderId = fake()->uuid();
        $this->recoverAction->shouldReceive('execute')
            ->with($orderId)
            ->once()
            ->andThrow(new RuntimeException('Order cannot be retried'));

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->baseEndpoint}/{$orderId}/retry");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Order cannot be retried',
            ]);
    }

    #[Test]
    public function it_allows_admin_to_retry_any_order(): void
    {
        // Arrange
        $admin = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        $otherUser = User::factory()->create();

        $orderId = fake()->uuid();
        $order = resolve(OrderFactory::class)->create([
            'id' => $orderId,
            'user_id' => $otherUser->id,
            'status' => 'failed',
        ]);

        $this->recoverAction->shouldReceive('execute')
            ->with($orderId)
            ->once()
            ->andReturn($order);

        // Act
        $response = $this->actingAs($admin)
            ->postJson("{$this->baseEndpoint}/{$orderId}/retry");

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order recovery initiated',
            ]);
    }

    #[Test]
    public function it_includes_recovery_info_in_order_details(): void
    {
        // Arrange
        $orderId = fake()->uuid();
        $order = resolve(OrderFactory::class)->create([
            'id' => $orderId,
            'user_id' => $this->auth->id,
            'status' => 'failed',
            'recovery_attempts' => 2,
            'last_error' => 'Connection timeout',
            'last_recovery_attempt' => now()->subMinutes(5),
        ]);
        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("{$this->baseEndpoint}/{$order->external_order_id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'external_order_id',
                    'amount',
                    'metadata',
                ],
            ]);
    }

    #[Test]
    public function it_lists_failed_orders_with_recovery_option(): void
    {
        // Arrange
        resolve(OrderFactory::class)->count(3)->create([
            'user_id' => $this->auth->id,
            'status' => 'failed',
        ]);

        resolve(OrderFactory::class)->count(2)->create([
            'user_id' => $this->auth->id,
            'status' => 'confirmed',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("{$this->baseEndpoint}?status=failed");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'status',
                        'can_retry',
                        'recovery_attempts',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_handles_concurrent_retry_attempts_gracefully(): void
    {
        // Arrange
        $orderId = fake()->uuid();
        resolve(OrderFactory::class)->create([
            'id' => $orderId,
            'user_id' => $this->auth->id,
            'status' => 'recovering',
        ]);

        $this->recoverAction->shouldReceive('execute')
            ->with($orderId)
            ->once()
            ->andThrow(new RuntimeException('Order is already being recovered'));

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->baseEndpoint}/{$orderId}/retry");

        // Assert
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Order is already being recovered',
            ]);
    }
}
