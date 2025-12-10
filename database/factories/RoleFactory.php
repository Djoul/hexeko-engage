<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $teamId = Team::firstOr(function () {
            return Team::factory()->create();
        })->id;

        return [
            'id' => Uuid::uuid4()->toString(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'team_id' => $teamId,
            'guard_name' => 'api',
            'is_protected' => false,
            'name' => 'test_role_'.$this->faker->unique()->uuid(),
        ];
    }

    /**
     * Create a role with a specific predefined name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
        ]);
    }

    /**
     * Create a protected role.
     */
    public function protected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_protected' => true,
        ]);
    }
}
