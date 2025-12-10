<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrigineInterfaces;
use App\Models\TranslationMigration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TranslationMigration>
 */
class TranslationMigrationFactory extends Factory
{
    protected $model = TranslationMigration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $interfaces = OrigineInterfaces::getValues();
        $interface = $this->faker->randomElement($interfaces);
        $version = sprintf('v%d.%d.%d',
            $this->faker->numberBetween(0, 5),
            $this->faker->numberBetween(0, 10),
            $this->faker->numberBetween(0, 20)
        );
        $date = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        return [
            'filename' => sprintf('%s_%s_%s.json', $interface, $version, $date),
            'interface_origin' => $interface,
            'version' => $version,
            'checksum' => hash('sha256', $this->faker->uuid()),
            'metadata' => [
                'user_id' => $this->faker->numberBetween(1, 100),
                'source' => $this->faker->randomElement(['manual_export', 'automatic_export', 'ci_cd']),
                'environment' => $this->faker->randomElement(['production', 'staging', 'development']),
                'keys_count' => $this->faker->numberBetween(50, 1000),
                'locales' => $this->faker->randomElements(['fr', 'en', 'es', 'de', 'it', 'pt'], $this->faker->numberBetween(2, 4)),
            ],
            'status' => 'pending',
            'batch_number' => null,
            'executed_at' => null,
            'rolled_back_at' => null,
            'error_message' => null,
        ];
    }

    /**
     * Indicate that the migration is completed.
     */
    public function completed(): Factory
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => 'completed',
                'batch_number' => $this->faker->numberBetween(1, 100),
                'executed_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            ];
        });
    }

    /**
     * Indicate that the migration is processing.
     */
    public function processing(): Factory
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => 'processing',
            ];
        });
    }

    /**
     * Indicate that the migration has failed.
     */
    public function failed(): Factory
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => 'failed',
                'error_message' => $this->faker->sentence(),
            ];
        });
    }

    /**
     * Indicate that the migration was rolled back.
     */
    public function rolledBack(): Factory
    {
        return $this->state(function (array $attributes): array {
            return [
                'status' => 'rolled_back',
                'batch_number' => $this->faker->numberBetween(1, 100),
                'executed_at' => $this->faker->dateTimeBetween('-6 months', '-1 day'),
                'rolled_back_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            ];
        });
    }

    /**
     * Set a specific interface origin.
     */
    public function forInterface(string $interface): Factory
    {
        return $this->state(function (array $attributes) use ($interface): array {
            $version = $attributes['version'] ?? sprintf('v%d.%d.%d',
                $this->faker->numberBetween(0, 5),
                $this->faker->numberBetween(0, 10),
                $this->faker->numberBetween(0, 20)
            );
            $date = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

            return [
                'interface_origin' => $interface,
                'filename' => sprintf('%s_%s_%s.json', $interface, $version, $date),
            ];
        });
    }

    /**
     * Set a specific version.
     */
    public function withVersion(string $version): Factory
    {
        return $this->state(function (array $attributes) use ($version): array {
            $interface = $attributes['interface_origin'] ?? OrigineInterfaces::WEB_FINANCER;
            $date = $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

            return [
                'version' => $version,
                'filename' => sprintf('%s_%s_%s.json', $interface, $version, $date),
            ];
        });
    }
}
