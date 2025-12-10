<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\Database\factories;

use App\Integrations\Payments\Stripe\Models\StripePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripePayment>
 */
class StripePaymentFactory extends Factory
{
    protected $model = StripePayment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stripe_payment_id' => null,
            'stripe_checkout_id' => 'cs_test_'.$this->faker->unique()->randomNumber(8),
            'status' => 'pending',
            'amount' => $this->faker->numberBetween(20, 1000),
            'currency' => $this->faker->randomElement(['eur', 'usd']),
            'credit_amount' => $this->faker->numberBetween(100, 10000),
            'credit_type' => $this->faker->randomElement(['voucher_credit', 'premium_credit']),
            'metadata' => [
                'product_name' => $this->faker->words(2, true),
            ],
            'error_message' => null,
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'stripe_payment_id' => 'pi_test_'.$this->faker->unique()->randomNumber(8),
            'processed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'failed',
            'error_message' => $this->faker->randomElement([
                'Your card was declined.',
                'Insufficient funds.',
                'Card expired.',
                'Invalid card number.',
            ]),
            'processed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'cancelled',
            'processed_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function withVoucherCredits(int $amount = 500): static
    {
        return $this->state(fn (array $attributes): array => [
            'credit_type' => 'voucher_credit',
            'credit_amount' => $amount,
        ]);
    }

    public function withPremiumCredits(int $amount = 1000): static
    {
        return $this->state(fn (array $attributes): array => [
            'credit_type' => 'premium_credit',
            'credit_amount' => $amount,
        ]);
    }
}
