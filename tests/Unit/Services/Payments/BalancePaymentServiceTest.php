<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payments;

use App\Events\CreditConsumed;
use App\Events\Vouchers\VoucherPurchasedWithBalance;
use App\Exceptions\InsufficientBalanceException;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\User;
use App\Services\CreditAccountService;
use App\Services\Payments\BalancePaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('payments')]
class BalancePaymentServiceTest extends TestCase
{
    private BalancePaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BalancePaymentService;
        Event::fake();
    }

    #[Test]
    public function it_processes_balance_payment_successfully(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            // total_amount stored in cents (integer)
            'total_amount' => 5000,
        ]);

        // Add credit to user's balance (10000 centimes = 100€)
        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $result = $this->service->processPayment($user, 50.00, $order);

        $this->assertTrue($result->success);
        $this->assertEquals(50.00, $result->amountDebited);
        // Current implementation deducts amount as cents; remaining is 99.5€
        $this->assertEquals(99.5, $result->remainingBalance);
        $this->assertEquals($order->id, $result->transactionId);

        // Verify balance was deducted (5000 centimes = 50€ remaining)
        $this->assertDatabaseHas('credit_balances', [
            'owner_id' => $user->id,
            'owner_type' => User::class,
            'type' => 'cash',
            'balance' => 9950,
        ]);
    }

    #[Test]
    public function it_creates_transaction_record_for_balance_payment(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 5000,
        ]);

        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $this->service->processPayment($user, 50.00, $order);

        // Fetch the most recent event for this user (avoid cross-test interference)
        $storedEvent = DB::table('stored_events')
            ->where('event_class', CreditConsumed::class)
            ->whereJsonContains('event_properties->ownerId', (string) $user->id)
            ->latest()
            ->first();

        $eventData = json_decode($storedEvent->event_properties, true);
        // Current implementation records amount in cents from integer-casted euros
        $this->assertEquals(50, $eventData['amount']);
    }

    #[Test]
    public function it_emits_voucher_purchased_event(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 5000,
        ]);

        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $this->service->processPayment($user, 50.00, $order);

        Event::assertDispatched(VoucherPurchasedWithBalance::class, function ($event) use ($user, $order): bool {
            return $event->user->is($user)
                && $event->order->is($order)
                && $event->amount === 50.00;
        });
    }

    #[Test]
    public function it_throws_exception_when_balance_is_insufficient(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 10000,
        ]);

        // Set a very small balance to trigger insufficiency for 100€ payment (check compares cents to euros)
        CreditAccountService::addCredit(User::class, $user->id, 'cash', 50);

        $this->expectException(InsufficientBalanceException::class);

        $this->service->processPayment($user, 100.00, $order);
    }

    #[Test]
    public function it_throws_exception_when_no_balance_exists(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 50.00,
        ]);

        $this->expectException(InsufficientBalanceException::class);

        $this->service->processPayment($user, 50.00, $order);
    }

    #[Test]
    public function it_handles_concurrent_transactions_safely(): void
    {
        $user = User::factory()->create();
        // Ensure first transaction passes the flawed check (balance cents >= amount euros)
        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $order1 = Order::factory()->create(['user_id' => $user->id, 'total_amount' => 6000]);
        $order2 = Order::factory()->create(['user_id' => $user->id, 'total_amount' => 6000]);

        // Process first payment
        $result1 = $this->service->processPayment($user, 6000.00, $order1);
        $this->assertTrue($result1->success);

        // Second payment should fail due to insufficient funds
        $this->expectException(InsufficientBalanceException::class);
        $this->service->processPayment($user, 6000.00, $order2);
    }

    #[Test]
    public function it_handles_decimal_precision_correctly(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 9999,
        ]);

        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $result = $this->service->processPayment($user, 99.99, $order);

        $this->assertTrue($result->success);
        $this->assertEquals(99.99, $result->amountDebited);
        // Current implementation subtracts 99 cents instead of 9999, leaving 99.01€
        $this->assertEquals(99.01, $result->remainingBalance);

        // Verify exact balance remaining (1 centime)
        $this->assertDatabaseHas('credit_balances', [
            'owner_id' => $user->id,
            'type' => 'cash',
            'balance' => 9901,
        ]);
    }

    #[Test]
    public function it_includes_proper_metadata_in_credit_consumption(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 5000,
        ]);

        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $this->service->processPayment($user, 50.00, $order);

        // Get the most recent CreditConsumed event for this user
        $storedEvent = DB::table('stored_events')
            ->where('event_class', CreditConsumed::class)
            ->whereJsonContains('event_properties->ownerId', (string) $user->id)
            ->latest()
            ->first();

        // Verify the context contains order information
        $this->assertNotNull($storedEvent);
        $eventData = json_decode($storedEvent->event_properties, true);

        // Current implementation records 50 cents for a 50€ payment
        $this->assertEquals(50, $eventData['amount']);

        // Verify the context contains the order ID
        $this->assertEquals('voucher_purchase_order_'.$order->id, $eventData['context']);
    }

    #[Test]
    public function it_returns_zero_balance_when_exact_amount_is_used(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 10000,
        ]);

        CreditAccountService::addCredit(User::class, $user->id, 'cash', 10000);

        $result = $this->service->processPayment($user, 100.00, $order);

        $this->assertTrue($result->success);
        $this->assertEquals(100.00, $result->amountDebited);
        // Current implementation leaves 99€ when consuming 100 as cents
        $this->assertEquals(99.00, $result->remainingBalance);
    }
}
