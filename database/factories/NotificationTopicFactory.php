<?php

namespace Database\Factories;

use App\Models\NotificationTopic;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;

/**
 * @extends Factory<NotificationTopic>
 */
class NotificationTopicFactory extends Factory
{
    protected $model = NotificationTopic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement([
            'marketing-updates',
            'product-news',
            'security-alerts',
            'promotions',
            'system-maintenance',
            'feature-announcements',
            'weekly-digest',
            'monthly-newsletter',
            'critical-updates',
            'tips-and-tricks',
        ]);

        $displayName = ucwords(str_replace('-', ' ', $name));

        return [
            'id' => Uuid::uuid7()->toString(),
            'name' => $name,
            'display_name' => $displayName,
            'description' => $this->faker->sentence(10),
            'financer_id' => null, // Global topics by default
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'subscriber_count' => 0,
        ];
    }

    /**
     * Indicate that the topic is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the topic is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a marketing topic.
     */
    public function marketing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'marketing',
            'display_name' => 'Marketing Updates',
            'description' => 'Receive updates about promotions, offers, and marketing campaigns',
            'is_active' => true,
            'subscriber_count' => 0,
        ]);
    }

    /**
     * Create a system alerts topic.
     */
    public function systemAlerts(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'system-alerts',
            'display_name' => 'System Alerts',
            'description' => 'Important system notifications and maintenance updates',
            'is_active' => true,
            'subscriber_count' => 0,
        ]);
    }
}
