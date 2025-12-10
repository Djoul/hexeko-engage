<?php

declare(strict_types=1);

namespace App\Integrations\Vouchers\Amilon\Database\seeds;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\OrderItemFactory;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Models\Team;
use App\Models\User;
use Artisan;
use Carbon\Carbon;
use Database\Seeders\BaseSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Random\RandomException;

class AmilonOrderSeeder extends BaseSeeder
{
    /** @var array<int, string> */
    private array $paymentMethods = [
        'card',
        'bank_transfer',
        'paypal',
    ];

    public function run(): void
    {
        $this->command->info('start syncing data from Amilon API');

        if (Category::count() === 0) {
            Artisan::call('amilon:sync-data');
        }

        $this->command->warn('Start creating orders for beneficiaries');

        if (! in_array(app()->environment(), ['local', 'staging', 'dev'])) {
            return;
        }

        DB::transaction(function (): void {
            Order::truncate();
            $this->createOrdersForBeneficiaries();
        });
    }

    private function createOrdersForBeneficiaries(): void
    {
        // Set the team context for permission queries
        $team = Team::first();

        if ($team !== null) {
            setPermissionsTeamId($team->id);
        }

        // Get all beneficiary users
        $beneficiaries = User::role(RoleDefaults::BENEFICIARY)->get();

        if ($beneficiaries->isEmpty()) {
            $this->command->warn('No beneficiary users found. Please run UserSeeder first.');

            return;
        }

        $products = Product::get();

        if ($products->isEmpty()) {
            $this->command->warn('No available products found.');

            return;
        }

        foreach ($beneficiaries as $beneficiary) {
            $orderCount = rand(1, 5);
            $this->createOrdersForUser($beneficiary, $products, $orderCount);
        }

        $this->command->info("Created orders for {$beneficiaries->count()} beneficiaries.");
    }

    /**
     * @param  Collection<int, Product>  $products
     */
    private function createOrdersForUser(User $user, Collection $products, int $orderCount): void
    {
        $baseDate = now()->subMonths(6);

        for ($i = 0; $i < $orderCount; $i++) {
            // Generate coherent dates
            $createdAt = $baseDate->copy()->addDays(rand(0, 180));
            $updatedAt = $createdAt->copy()->addMinutes(rand(5, 120));

            // Select random product
            $product = $products->random();

            /** @var Product $product */
            $merchant = Merchant::where('merchant_id', $product->merchant_id)->first();

            // Skip if merchant not found
            if (! $merchant) {
                $this->command->warn("Merchant not found for product ID: $product->id (merchant_id: $product->merchant_id)");

                continue;
            }

            // Determine order status
            $orderStatus = $this->getRealisticOrderStatus($createdAt);
            $paymentStatus = $this->getPaymentStatusForOrderStatus($orderStatus);

            // Map 'completed' to 'confirmed' for the Order model
            $mappedOrderStatus = $orderStatus === 'completed' ? 'confirmed' : $orderStatus;

            // Generate voucher details for confirmed orders
            $voucherCode = null;
            if ($mappedOrderStatus === 'confirmed') {
                $voucherCode = $this->generateVoucherCode($merchant->merchant_id);
            }

            // Create order
            try {
                $paymentId = 'pi_'.bin2hex(random_bytes(12));
            } catch (RandomException) {
                $paymentId = 'pi_'.uniqid().uniqid();
            }
            $order = resolve(OrderFactory::class)->create([
                'user_id' => $user->id,
                'merchant_id' => $merchant->merchant_id,
                'product_id' => $product->id,
                'external_order_id' => 'ENGAGE_'.strtoupper(uniqid()),
                'order_id' => 'ORD_'.strtoupper(uniqid()),
                'amount' => $product->price,
                'status' => $mappedOrderStatus,
                'payment_id' => $paymentId,
                // 'payment_status' => $paymentStatus, // Column removed
                'payment_method' => $this->paymentMethods[array_rand($this->paymentMethods)],
                // 'payment_completed_at' => $paymentCompletedAt, // Column removed
                'voucher_code' => $voucherCode,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            // Create order items
            if ($order instanceof Order) {
                $this->createOrderItems($order, $product);
            }

            // Add recovery attempts for failed orders
            if ($orderStatus === 'failed' && rand(0, 1) && $order instanceof Order) {
                $this->addRecoveryAttempts($order, $createdAt);
            }
        }
    }

    private function createOrderItems(Order $order, Product $product): void
    {
        // Most orders have 1 item, some have 2-3
        $itemCount = rand(1, 100) <= 80 ? 1 : rand(2, 3);

        for ($i = 0; $i < $itemCount; $i++) {
            $quantity = rand(1, 3);
            $vouchers = [];

            // Generate voucher details for confirmed orders
            if ($order->status === 'confirmed') {
                for ($j = 0; $j < $quantity; $j++) {
                    $vouchers[] = [
                        'code' => $order->merchant !== null ? $this->generateVoucherCode($order->merchant->merchant_id) : '',
                        'pin' => $this->generatePin(),
                        'amount' => $product->price,
                        'expires_at' => now()->addYear()->toIso8601String(),
                    ];
                }
            }

            resolve(OrderItemFactory::class)->create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $product->price,
                'vouchers' => $vouchers,
            ]);
        }
    }

    private function addRecoveryAttempts(Order $order, Carbon $baseDate): void
    {
        $attempts = rand(1, 3);
        $lastAttempt = $baseDate->copy()->addHours(rand(1, 24));

        $order->update([
            'recovery_attempts' => $attempts,
            'last_error' => 'Payment failed: Insufficient funds',
            'last_recovery_attempt' => $lastAttempt,
            'next_retry_at' => $lastAttempt->copy()->addHours(rand(6, 24)),
        ]);
    }

    private function getRealisticOrderStatus(Carbon $createdAt): string
    {
        $daysOld = $createdAt->diffInDays(now());

        // Older orders are more likely to be completed
        if ($daysOld > 30) {
            return rand(1, 100) <= 85 ? 'completed' : 'failed';
        }

        // Recent orders might still be processing
        if ($daysOld < 7) {
            $rand = rand(1, 100);
            if ($rand <= 60) {
                return 'completed';
            }
            if ($rand <= 80) {
                return 'processing';
            }
            if ($rand <= 90) {
                return 'pending';
            }

            return 'failed';
        }

        // Mid-range orders
        return rand(1, 100) <= 75 ? 'completed' : 'failed';
    }

    private function getPaymentStatusForOrderStatus(string $orderStatus): string
    {
        return match ($orderStatus) {
            'completed' => 'succeeded',
            'processing' => 'processing',
            'failed' => rand(0, 1) !== 0 ? 'failed' : 'cancelled',
            'cancelled' => 'cancelled',
            default => 'pending',
        };
    }

    private function generateVoucherCode(string $merchantId): string
    {
        try {
            return strtoupper($merchantId.'_'.bin2hex(random_bytes(8)));
        } catch (RandomException) {
            return strtoupper($merchantId.'_'.uniqid());
        }
    }

    private function generatePin(): string
    {
        return (string) rand(100000, 999999);
    }
}
