<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Financer;
use App\Models\MobileVersionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileVersionLog>
 */
class MobileVersionLogFactory extends Factory
{
    protected $model = MobileVersionLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $financer = Financer::inRandomOrder()->first();
        if (! $financer instanceof Financer) {
            $financer = Financer::factory()->create();
        }

        $user = User::factory()->create();

        return [
            'financer_id' => $financer->id,
            'user_id' => $user->id,
            'platform' => $this->faker->randomElement(['ios', 'android']),
            'version' => $this->faker->semver(),
            'minimum_required_version' => $this->faker->semver(),
            'should_update' => $this->faker->boolean(),
            'update_type' => $this->faker->randomElement(['soft_required', 'soft_optional', 'store_required', 'store_optional']),
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
            'metadata' => [
                'device' => 'hello',
            ],
        ];
    }
}
