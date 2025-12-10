<?php

namespace Database\Factories;

use App\Enums\NotificationTypes;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushNotification>
 */
class PushNotificationFactory extends Factory
{
    protected $model = PushNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'notification_id' => Str::uuid()->toString(),
            'external_id' => null,
            'delivery_type' => $this->faker->randomElement(['targeted', 'broadcast']),
            'device_count' => $this->faker->numberBetween(0, 100),
            'type' => $this->faker->randomElement(NotificationTypes::getValues()),
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraph(2),
            'url' => $this->faker->optional()->url(),
            'image' => $this->faker->optional()->imageUrl(),
            'icon' => $this->faker->optional()->imageUrl(64, 64),
            'data' => [],
            'buttons' => [],
            'priority' => $this->faker->randomElement(['low', 'normal', 'high', 'urgent']),
            'ttl' => 86400,
            'status' => $this->faker->randomElement(['draft', 'scheduled', 'sending', 'sent', 'failed', 'cancelled']),
            'recipient_count' => $this->faker->numberBetween(0, 1000),
            'delivered_count' => $this->faker->numberBetween(0, 500),
            'opened_count' => $this->faker->numberBetween(0, 200),
            'clicked_count' => $this->faker->numberBetween(0, 100),
            'scheduled_at' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'sent_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'author_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the notification has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'sent',
            'sent_at' => now(),
            'external_id' => 'onesignal-'.Str::random(10),
        ]);
    }

    /**
     * Indicate that the notification is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'scheduled',
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 week'),
        ]);
    }

    /**
     * Indicate that the notification is a broadcast.
     */
    public function broadcast(): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_type' => 'broadcast',
            'device_count' => $this->faker->numberBetween(100, 10000),
            'recipient_count' => $this->faker->numberBetween(100, 10000),
        ]);
    }

    /**
     * Indicate that the notification is targeted.
     */
    public function targeted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'delivery_type' => 'targeted',
            'device_count' => $this->faker->numberBetween(1, 10),
            'recipient_count' => $this->faker->numberBetween(1, 10),
        ]);
    }
}
