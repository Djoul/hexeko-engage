<?php

namespace App\Integrations\Vouchers\Amilon\Database\factories;

use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Order;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(10, 1000);
        $paymentMethod = $this->faker->randomElement(['balance', 'stripe', 'mixed']);

        return [
            'amount' => $amount,
            'total_amount' => $amount,
            'external_order_id' => $this->faker->unique()->uuid(),
            'order_id' => $this->faker->word(),
            'status' => $this->faker->word(),
            'price_paid' => $this->faker->numberBetween(10, 1000),
            'voucher_url' => $this->faker->url(),
            'user_id' => User::factory(),
            'payment_id' => $this->faker->word(),
            'payment_method' => $paymentMethod,
            'stripe_payment_id' => $paymentMethod === 'stripe' || $paymentMethod === 'mixed'
                ? 'pi_test_'.$this->faker->uuid()
                : null,
            'balance_amount_used' => $paymentMethod === 'balance'
                ? $amount
                : ($paymentMethod === 'mixed' ? $this->faker->numberBetween(10, $amount) : null),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
            'order_date' => Date::now(),
            'order_status' => $this->faker->numberBetween(1, 100),
            'gross_amount' => $this->faker->numberBetween(10, 1000),
            'net_amount' => $this->faker->numberBetween(10, 1000),
            'total_requested_codes' => $this->faker->numberBetween(1, 100),
            'last_recovery_attempt' => null,
            'next_retry_at' => null,

            'merchant_id' => function () {
                /** @var Merchant $merchant */
                $merchant = resolve(MerchantFactory::class)->create();

                return $merchant->merchant_id;
            },
        ];
    }

    /**
     * Set a scheduled next retry time for the order
     */
    public function withNextRetry(?DateTimeInterface $nextRetry = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'next_retry_at' => $nextRetry ?? Date::now()->addMinutes(30),
        ]);
    }
}
