<?php

namespace Database\Factories;

use App\Enums\DeviceTypes;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $deviceType = $this->faker->randomElement(DeviceTypes::values());

        return [
            'user_id' => User::factory(),
            'subscription_id' => 'onesignal-'.$this->faker->uuid(),
            'device_type' => $deviceType,
            'device_model' => $this->getDeviceModel($deviceType),
            'device_os' => $this->getDeviceOs($deviceType),
            'app_version' => $this->faker->semver(),
            'push_enabled' => $this->faker->boolean(90),
            'sound_enabled' => $this->faker->boolean(85),
            'vibration_enabled' => $this->faker->boolean(80),
            'tags' => $this->faker->randomElement([
                [],
                ['premium' => true],
                ['locale' => $this->faker->locale()],
                ['beta' => true, 'locale' => $this->faker->locale()],
            ]),
            'metadata' => [],
            'last_active_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Indicate that the subscription is for a guest device.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that push notifications are disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'push_enabled' => false,
        ]);
    }

    /**
     * Indicate that the subscription is for a specific device type.
     */
    public function deviceType(DeviceTypes $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'device_type' => $type,
            'device_model' => $this->getDeviceModel($type),
            'device_os' => $this->getDeviceOs($type),
        ]);
    }

    /**
     * Get a realistic device model based on device type.
     */
    private function getDeviceModel(string|DeviceTypes $type): string
    {
        $typeValue = $type instanceof DeviceTypes ? $type->value : $type;

        return match ($typeValue) {
            DeviceTypes::IOS => $this->faker->randomElement([
                'iPhone 14 Pro', 'iPhone 14', 'iPhone 13', 'iPhone 12', 'iPad Pro', 'iPad Air',
            ]),
            DeviceTypes::ANDROID => $this->faker->randomElement([
                'Samsung Galaxy S23', 'Google Pixel 7', 'OnePlus 11', 'Xiaomi 13',
            ]),
            DeviceTypes::WEB => $this->faker->randomElement([
                'Chrome', 'Firefox', 'Safari', 'Edge',
            ]),
            DeviceTypes::DESKTOP => $this->faker->randomElement([
                'Windows App', 'macOS App', 'Linux App',
            ]),
        };
    }

    /**
     * Get a realistic OS version based on device type.
     */
    private function getDeviceOs(string|DeviceTypes $type): string
    {
        $typeValue = $type instanceof DeviceTypes ? $type->value : $type;

        return match ($typeValue) {
            DeviceTypes::IOS => 'iOS '.$this->faker->randomElement(['16.0', '16.5', '17.0', '17.1']),
            DeviceTypes::ANDROID => 'Android '.$this->faker->randomElement(['12', '13', '14']),
            DeviceTypes::WEB => $this->faker->randomElement([
                'Windows 11', 'macOS Ventura', 'Ubuntu 22.04',
            ]),
            DeviceTypes::DESKTOP => $this->faker->randomElement([
                'Windows 11', 'macOS Sonoma', 'Ubuntu 23.10',
            ]),
        };
    }
}
