<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\DTO\RecoveryResult;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Integrations\Vouchers\Amilon\Services\VoucherRecoveryService;
use App\Services\Payments\BalancePaymentService;
use App\Services\Payments\PaymentResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\Helpers\Traits\AmilonDatabaseCleanup;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class VoucherRecoveryServiceRefactoredTest extends TestCase
{
    use AmilonDatabaseCleanup;
    use DatabaseTransactions;

    private VoucherRecoveryService $service;

    private AmilonOrderService $orderService;

    private BalancePaymentService $balanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanupAmilonDatabase();

        // Clean up any existing orders to ensure test isolation
        Order::query()->delete();

        $this->orderService = Mockery::mock(AmilonOrderService::class);
        $this->balanceService = Mockery::mock(BalancePaymentService::class);
        $this->service = new VoucherRecoveryService($this->orderService, $this->balanceService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_identifies_only_error_status_orders_for_recovery(): void
    {
        // Get initial count
        $initialOrderCount = Order::count();

        // Arrange - Create actual database records
        $errorOrder1 = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 0,
            'created_at' => Carbon::now()->subDays(10), // Old order, but should still be included
        ]);

        $errorOrder2 = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 2,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        // Create orders that should NOT be included
        resolve(OrderFactory::class)->create([
            'status' => OrderStatus::CONFIRMED,
            'recovery_attempts' => 0,
        ]);

        resolve(OrderFactory::class)->create([
            'status' => OrderStatus::CANCELLED,
            'recovery_attempts' => 0,
        ]);

        resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 3, // Max attempts reached
        ]);

        // Act
        $failedOrders = $this->service->identifyFailedOrders();

        // Assert
        $this->assertInstanceOf(Collection::class, $failedOrders);
        $this->assertCount(2, $failedOrders);
        $this->assertTrue($failedOrders->contains($errorOrder1));
        $this->assertTrue($failedOrders->contains($errorOrder2));

        // Verify total orders created
        $this->assertEquals($initialOrderCount + 5, Order::count());
    }

    #[Test]
    public function it_successfully_recovers_order_and_debits_balance(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create(['net_price' => 5000]);
        $order = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 0,
            'user_id' => $user->id,
            'product_id' => $product->id,
            'amount' => 5000,
            'external_order_id' => 'external-123',
        ]);

        $this->orderService->shouldReceive('createOrder')
            ->with(
                Mockery::on(fn ($p): bool => $p->id === $product->id),
                1,
                'external-123',
                (string) $user->id
            )
            ->once()
            ->andReturn(['status' => 'success']);

        $paymentResult = new PaymentResult(
            success: true,
            amountDebited: 5000,
            transactionId: '123',
            remainingBalance: 10000
        );

        $this->balanceService->shouldReceive('processPayment')
            ->with(
                Mockery::on(fn ($u): bool => $u->id === $user->id),
                5000,
                Mockery::on(fn ($o): bool => $o->id === $order->id)
            )
            ->once()
            ->andReturn($paymentResult);

        // Act
        $result = $this->service->attemptRecovery($order);

        // Assert
        $this->assertInstanceOf(RecoveryResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('Order successfully recovered', $result->message);
        $this->assertEquals(OrderStatus::CONFIRMED, $result->newStatus);

        // Verify order was updated
        $order->refresh();
        $this->assertEquals(OrderStatus::CONFIRMED, $order->status);
        $this->assertEquals(1, $order->recovery_attempts);
        $this->assertEquals(5000, $order->balance_amount_used);
        $this->assertNull($order->last_error);
    }

    #[Test]
    public function it_marks_order_as_cancelled_after_three_failed_attempts(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();
        $order = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 2, // Already 2 attempts
            'user_id' => $user->id,
            'product_id' => $product->id,
            'external_order_id' => 'external-123',
        ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andThrow(new Exception('API error'));

        // Act
        $result = $this->service->attemptRecovery($order);

        // Assert
        $this->assertFalse($result->success);
        $this->assertEquals(OrderStatus::CANCELLED, $result->newStatus);
        $this->assertStringContainsString('API error', $result->error);

        // Verify order was updated
        $order->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
        $this->assertEquals(3, $order->recovery_attempts);
        $this->assertStringContainsString('API error', $order->last_error);
    }

    #[Test]
    public function it_keeps_order_in_error_status_when_attempts_remain(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'test@example.com']);
        $merchant = resolve(MerchantFactory::class)->create();
        $product = resolve(ProductFactory::class)->forMerchant($merchant)->create();
        $order = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 1, // Only 1 attempt
            'user_id' => $user->id,
            'product_id' => $product->id,
            'external_order_id' => 'external-123',
        ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andThrow(new Exception('Temporary error'));

        // Act
        $result = $this->service->attemptRecovery($order);

        // Assert
        $this->assertFalse($result->success);
        $this->assertEquals(OrderStatus::ERROR, $result->newStatus);

        // Verify order was updated
        $order->refresh();
        $this->assertEquals(OrderStatus::ERROR, $order->status);
        $this->assertEquals(2, $order->recovery_attempts);
    }

    #[Test]
    public function it_only_allows_retry_for_error_status_with_remaining_attempts(): void
    {
        // Arrange
        $errorOrder = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 1,
        ]);

        $cancelledOrder = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::CANCELLED,
            'recovery_attempts' => 1,
        ]);

        $confirmedOrder = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::CONFIRMED,
            'recovery_attempts' => 0,
        ]);

        $maxAttemptsOrder = resolve(OrderFactory::class)->create([
            'status' => OrderStatus::ERROR,
            'recovery_attempts' => 3,
        ]);

        // Act & Assert
        $this->assertTrue($this->service->canRetry($errorOrder));
        $this->assertFalse($this->service->canRetry($cancelledOrder));
        $this->assertFalse($this->service->canRetry($confirmedOrder));
        $this->assertFalse($this->service->canRetry($maxAttemptsOrder));
    }
}
