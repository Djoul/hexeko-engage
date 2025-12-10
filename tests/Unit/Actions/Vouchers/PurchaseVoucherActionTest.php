<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Vouchers;

use App\Actions\Vouchers\PurchaseVoucherAction;
use App\Events\Vouchers\VoucherPurchaseError;
use App\Events\Vouchers\VoucherPurchaseNotification;
use App\Exceptions\InsufficientBalanceException;
use App\Integrations\Vouchers\Amilon\Enums\OrderStatus;
use App\Integrations\Vouchers\Amilon\Exceptions\AmilonOrderErrorException;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonOrderService;
use App\Models\CreditBalance;
use App\Services\Payments\BalancePaymentService;
use App\Services\Payments\PaymentResult;
use Exception;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class PurchaseVoucherActionTest extends TestCase
{
    private PurchaseVoucherAction $action;

    private MockInterface $balanceService;

    private MockInterface $amilonService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->balanceService = Mockery::mock(BalancePaymentService::class);
        $this->amilonService = Mockery::mock(AmilonOrderService::class);
        $this->app->instance(BalancePaymentService::class, $this->balanceService);
        $this->app->instance(AmilonOrderService::class, $this->amilonService);

        $this->action = new PurchaseVoucherAction($this->balanceService, $this->amilonService);

        Event::fake();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_purchases_voucher_with_balance_only(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id, // Use merchant_id, not id
            'price' => 5000,      // 50 euros in cents
            'net_price' => 5000,  // 50 euros in cents
            'name' => 'Test Voucher',
            'is_available' => true,
        ]);

        // Create credit balance
        CreditBalance::create([
            'owner_id' => (string) $user->id,
            'owner_type' => get_class($user),
            'type' => 'cash',
            'balance' => 10000, // 100 euros in cents
        ]);

        $this->balanceService
            ->shouldReceive('processPayment')
            ->once()
            ->withAnyArgs()
            ->andReturn(new PaymentResult(
                success: true,
                amountDebited: 5000,  // Amount in cents
                transactionId: 'TXN123',
                remainingBalance: 5000  // Amount in cents
            ));

        // Mock Amilon service to return an order array
        $mockedOrder = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'payment_method' => 'balance',
            'total_amount' => 5000,  // Amount in cents
        ]);

        $this->amilonService
            ->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'order' => $mockedOrder,
                'vouchers' => [
                    ['voucherLink' => 'https://example.com/voucher', 'pin' => 'PIN123'],
                ],
            ]);

        // Act
        $result = $this->action->execute([
            'product_id' => $product->id,
            'payment_method' => 'balance',
        ]);

        // Assert
        $this->assertEquals('balance', $result['payment_method']);
        $this->assertEquals(5000, $result['amount_paid']); // 50 euros in cents
        $this->assertEquals(5000, $result['remaining_balance']); // 50 euros in cents

        // Verify order was created
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'payment_method' => 'balance',
            'total_amount' => 5000,  // Amount in cents
        ]);

        Event::assertDispatched(VoucherPurchaseNotification::class, function ($event) use ($user): bool {
            return $event->userId === $user->id && $event->status === 'completed';
        });
    }

    #[Test]
    public function it_records_stripe_payment_method_with_reference(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id, // Use merchant_id, not id
            'price' => 10000,     // 100 euros in cents
            'net_price' => 0,     // No balance payment for stripe-only
            'name' => 'Premium Voucher',
            'is_available' => true,
        ]);

        // No balance payment needed for Stripe

        // Mock Amilon service to return an order array
        $mockedOrder = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'payment_method' => 'stripe',
            'stripe_payment_id' => 'pi_test_123456',
            'total_amount' => 10000,  // Amount in cents
        ]);

        $this->amilonService
            ->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'order' => $mockedOrder,
                'vouchers' => [
                    ['voucherLink' => 'https://example.com/voucher', 'pin' => 'PIN456'],
                ],
            ]);

        // Act
        $result = $this->action->execute([
            'product_id' => $product->id,
            'payment_method' => 'stripe',
            'stripe_payment_id' => 'pi_test_123456', // Reference to Stripe payment
        ]);

        // Assert
        $this->assertEquals('stripe', $result['payment_method']);
        $this->assertEquals(0, $result['amount_paid']); // No balance used
        $this->assertFalse(array_key_exists('requires_stripe_payment', $result)); // Already processed by Stripe integration

        // Verify order was created with Stripe reference
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'payment_method' => 'stripe',
            'stripe_payment_id' => 'pi_test_123456',
            'total_amount' => 10000,  // Amount in cents
        ]);
    }

    #[Test]
    public function it_handles_mixed_payment_with_balance_and_stripe_reference(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id, // Use merchant_id, not id
            'price' => 15000,     // 150 euros in cents
            'net_price' => 5000,  // 50 euros in cents - Balance portion that will be debited
            'name' => 'Mixed Payment Voucher',
            'is_available' => true,
        ]);

        // Create partial credit balance
        CreditBalance::create([
            'owner_id' => (string) $user->id,
            'owner_type' => get_class($user),
            'type' => 'cash',
            'balance' => 5000, // 50 euros in cents
        ]);

        $this->balanceService
            ->shouldReceive('processPayment')
            ->once()
            ->withAnyArgs()
            ->andReturn(new PaymentResult(
                success: true,
                amountDebited: 5000,  // Amount in cents
                transactionId: 'TXN456',
                remainingBalance: 0  // Amount in cents
            ));

        // Mock Amilon service to return an order array
        $mockedOrder = Order::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'payment_method' => 'mixed',
            'stripe_payment_id' => 'pi_test_mixed_789',
            'balance_amount_used' => 5000,  // Amount in cents
            'total_amount' => 15000,  // Amount in cents
        ]);

        $this->amilonService
            ->shouldReceive('createOrder')
            ->once()
            ->andReturn([
                'order' => $mockedOrder,
                'vouchers' => [
                    ['voucherLink' => 'https://example.com/voucher', 'pin' => 'PIN789'],
                ],
            ]);

        // Act
        $result = $this->action->execute([
            'product_id' => $product->id,
            'payment_method' => 'mixed',
            'balance_amount' => 5000, // Amount paid with balance in cents (50€)
            'stripe_payment_id' => 'pi_test_mixed_789', // Reference to Stripe payment for remaining 100€
        ]);

        // Assert
        $this->assertEquals('mixed', $result['payment_method']);
        $this->assertEquals(5000, $result['amount_paid']); // Balance portion paid
        $this->assertEquals(5000, $result['balance_amount']); // 50 euros from balance
        $this->assertEquals(10000, $result['stripe_amount']); // 100 euros via Stripe

        // Verify order was created with both references
        $this->assertDatabaseHas('int_vouchers_amilon_orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'payment_method' => 'mixed',
            'stripe_payment_id' => 'pi_test_mixed_789',
            'balance_amount_used' => 5000,  // Amount in cents
            'total_amount' => 15000,  // Amount in cents
        ]);
    }

    #[Test]
    public function it_throws_exception_for_insufficient_balance(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id, // Use merchant_id, not id
            'price' => 10000,     // 100 euros in cents
            'net_price' => 10000, // 100 euros in cents - Full amount to debit
            'is_available' => true,
        ]);

        // Create insufficient balance
        CreditBalance::create([
            'owner_id' => (string) $user->id,
            'owner_type' => get_class($user),
            'type' => 'cash',
            'balance' => 2000, // Only 20 euros
        ]);

        // Mock the balance service to throw insufficient balance exception
        $this->balanceService
            ->shouldReceive('processPayment')
            ->once()
            ->andThrow(new InsufficientBalanceException('Insufficient balance'));

        // Act & Assert
        $this->expectException(InsufficientBalanceException::class);

        $this->action->execute([
            'product_id' => $product->id,
            'payment_method' => 'balance',
        ]);
    }

    #[Test]
    public function it_validates_product_availability(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id, // Use merchant_id, not id
            'price' => 5000,  // Amount in cents
            'is_available' => false,
        ]);

        // Act
        $result = $this->action->execute([
            'product_id' => $product->id,
            'payment_method' => 'balance',
        ]);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals('Product is not available for purchase', $result['message']);
    }

    #[Test]
    public function it_broadcasts_error_event_on_failure(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id, // Use merchant_id, not id
            'price' => 5000,  // Amount in cents
            'net_price' => 5000, // 50 euros in cents
            'is_available' => true,
        ]);

        $this->balanceService
            ->shouldReceive('processPayment')
            ->once()
            ->andThrow(new Exception('Payment processing failed'));

        // Act
        try {
            $this->action->execute([
                'product_id' => $product->id,
                'payment_method' => 'balance',
            ]);
        } catch (Exception $e) {
            // Expected
        }

        // Assert
        Event::assertDispatched(VoucherPurchaseError::class, function ($event) use ($user): bool {
            return $event->userId === $user->id
                && $event->errorCode === 'PAYMENT_FAILED'
                && $event->errorMessage === 'Payment processing failed';
        });
    }

    #[Test]
    public function it_handles_amilon_api_error_and_restores_balance(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $this->actingAs($user);

        // Create merchant first
        $merchant = Merchant::factory()->create();

        $product = Product::factory()->create([
            'merchant_id' => $merchant->merchant_id,
            'price' => 5000,      // 50 euros in cents
            'net_price' => 5000,  // 50 euros in cents
            'name' => 'Test Voucher',
            'is_available' => true,
        ]);

        // Create credit balance
        $initialBalance = 10000; // 100 euros in cents
        CreditBalance::create([
            'owner_id' => (string) $user->id,
            'owner_type' => get_class($user),
            'type' => 'cash',
            'balance' => $initialBalance,
        ]);

        // Mock balance service to process payment successfully
        $this->balanceService
            ->shouldReceive('processPayment')
            ->once()
            ->withAnyArgs()
            ->andReturn(new PaymentResult(
                success: true,
                amountDebited: 5000,  // Amount in cents
                transactionId: 'TXN123',
                remainingBalance: 5000  // Amount in cents
            ));

        // Mock Amilon service to throw AmilonOrderErrorException
        $this->amilonService
            ->shouldReceive('createOrder')
            ->once()
            ->andThrow(new AmilonOrderErrorException('Amilon API is down'));

        // Act & Assert
        $this->expectException(AmilonOrderErrorException::class);
        $this->expectExceptionMessage('Amilon API is down');

        try {
            $this->action->execute([
                'product_id' => $product->id,
                'payment_method' => 'balance',
            ]);
        } catch (AmilonOrderErrorException $e) {
            // Verify order was created with ERROR status
            $this->assertDatabaseHas('int_vouchers_amilon_orders', [
                'user_id' => $user->id,
                'product_id' => $product->id,
                'status' => OrderStatus::ERROR,
                'last_error' => 'Amilon API is down',
                'balance_amount_used' => 0, // Should be reset to 0 after refund
            ]);

            // Verify error event was dispatched
            Event::assertDispatched(VoucherPurchaseError::class, function ($event) use ($user): bool {
                return $event->userId === $user->id
                    && $event->errorCode === 'AMILON_API_ERROR'
                    && str_contains($event->errorMessage, 'Amilon API error');
            });

            // Verify balance restoration notification was sent
            Event::assertDispatched(VoucherPurchaseNotification::class, function ($event) use ($user): bool {
                return $event->userId === $user->id
                    && $event->status === 'balance_restored';
            });

            throw $e; // Re-throw to satisfy the expectException
        }
    }
}
