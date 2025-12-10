<?php

namespace Tests\Feature\Http\Controllers\V1\PushNotifications;

use App\Models\NotificationTopic;
use App\Models\PushSubscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('push')]
#[Group('notification')]
class NotificationTopicControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private string $endpoint = '/api/v1/push/topics';

    #[Test]
    public function it_lists_all_available_topics(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();

        NotificationTopic::factory()->count(5)->create([
            'is_active' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson($this->endpoint);

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'description',
                        'is_active',
                        'subscriber_count',
                    ],
                ],
            ])
            ->assertJsonCount(5, 'data');
    }

    #[Test]
    public function it_subscribes_to_a_topic(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        $topic = NotificationTopic::factory()->create([
            'name' => 'marketing',
            'is_active' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->endpoint}/{$topic->id}/subscribe", [
                'subscription_id' => $subscription->subscription_id,
            ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => 'Successfully subscribed to topic',
                ],
            ]);

        // Verify in database
        $this->assertDatabaseHas('notification_topic_subscriptions', [
            'notification_topic_id' => $topic->id,
            'push_subscription_id' => $subscription->id,
        ]);
    }

    #[Test]
    public function it_unsubscribes_from_a_topic(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        $topic = NotificationTopic::factory()->create([
            'name' => 'promotions',
            'is_active' => true,
        ]);

        // Subscribe first
        $subscription->topics()->attach($topic, ['subscribed_at' => now()]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->endpoint}/{$topic->id}/unsubscribe", [
                'subscription_id' => $subscription->subscription_id,
            ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => 'Successfully unsubscribed from topic',
                ],
            ]);

        // Verify removed from database
        $this->assertDatabaseMissing('notification_topic_subscriptions', [
            'notification_topic_id' => $topic->id,
            'push_subscription_id' => $subscription->id,
        ]);
    }

    #[Test]
    public function it_lists_user_topic_subscriptions(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        $topics = NotificationTopic::factory()->count(3)->create([
            'is_active' => true,
        ]);

        // Subscribe to 2 topics
        $subscription->topics()->attach([
            $topics[0]->id => ['subscribed_at' => now()],
            $topics[1]->id => ['subscribed_at' => now()],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("{$this->endpoint}/subscriptions");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'subscribed',
                        'subscribed_at',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');

        // Check subscription status
        $data = $response->json('data');
        $subscribedTopics = collect($data)->where('subscribed', true)->count();
        $this->assertEquals(2, $subscribedTopics);
    }

    #[Test]
    public function it_prevents_subscribing_to_inactive_topic(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        $topic = NotificationTopic::factory()->create([
            'name' => 'deprecated-topic',
            'is_active' => false,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->endpoint}/{$topic->id}/subscribe", [
                'subscription_id' => $subscription->subscription_id,
            ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Cannot subscribe to inactive topic',
            ]);
    }

    #[Test]
    public function it_handles_duplicate_subscription_gracefully(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        $topic = NotificationTopic::factory()->create([
            'name' => 'news',
            'is_active' => true,
        ]);

        // Already subscribed
        $subscription->topics()->attach($topic, ['subscribed_at' => now()]);

        // Act - try to subscribe again
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->endpoint}/{$topic->id}/subscribe", [
                'subscription_id' => $subscription->subscription_id,
            ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'message' => 'Already subscribed to topic',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_topic(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->endpoint}/99999999-9999-9999-9999-999999999999/subscribe", [
                'subscription_id' => $subscription->subscription_id,
            ]);

        // Assert
        $response->assertNotFound()
            ->assertJson([
                'message' => 'Topic not found',
            ]);
    }

    #[Test]
    public function it_bulk_subscribes_to_multiple_topics(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser();
        $subscription = PushSubscription::factory()->create([
            'user_id' => $this->auth->id,
        ]);

        $topics = NotificationTopic::factory()->count(3)->create([
            'is_active' => true,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson("{$this->endpoint}/bulk-subscribe", [
                'subscription_id' => $subscription->subscription_id,
                'topic_ids' => $topics->pluck('id')->toArray(),
            ]);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'success' => true,
                    'subscribed_count' => 3,
                ],
            ]);

        // Verify all subscriptions
        foreach ($topics as $topic) {
            $this->assertDatabaseHas('notification_topic_subscriptions', [
                'notification_topic_id' => $topic->id,
                'push_subscription_id' => $subscription->id,
            ]);
        }
    }
}
