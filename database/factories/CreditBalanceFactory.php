<?php

namespace Database\Factories;

use App\Enums\CreditTypes;
use App\Models\CreditBalance;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditBalanceFactory extends Factory
{
    protected $model = CreditBalance::class;

    public function definition(): array
    {
        return [
            'owner_type' => $this->faker->randomElement([User::class, Financer::class]),
            'owner_id' => function (array $attributes) {
                if ($attributes['owner_type'] === User::class) {
                    return User::factory()->create()->id;
                }

                return Financer::factory()->create()->id;
            },
            'type' => $this->faker->randomElement(CreditTypes::asArray()),
            'balance' => $this->faker->numberBetween(0, 10000),
            'context' => [
                'description' => $this->faker->sentence(),
                'source' => $this->faker->randomElement(['manual', 'system', 'integration']),
            ],
        ];
    }

    public function forUser($user = null): static
    {
        return $this->state(function (array $attributes) use ($user): array {
            $userId = $user ? $user->id : User::factory()->create()->id;

            return [
                'owner_type' => User::class,
                'owner_id' => $userId,
            ];
        });
    }

    public function forFinancer($financer = null): static
    {
        return $this->state(function (array $attributes) use ($financer): array {
            $financerId = $financer ? $financer->id : Financer::factory()->create()->id;

            return [
                'owner_type' => Financer::class,
                'owner_id' => $financerId,
            ];
        });
    }

    public function amilonType(): static
    {
        return $this->state(function (array $attributes): array {
            return [
                'type' => 'amilon',
            ];
        });
    }
}
