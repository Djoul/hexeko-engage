<?php

namespace App\Http\Controllers\V1\Push;

use App\Http\Controllers\Controller;
use App\Http\Resources\Push\TopicResource;
use App\Models\NotificationTopic;
use App\Models\PushSubscription;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Notifications/Push')]
class NotificationTopicController extends Controller
{
    /**
     * List all available notification topics
     */
    public function index(): JsonResponse
    {
        $topics = NotificationTopic::where('is_active', true)
            ->withCount('subscriptions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => TopicResource::collection($topics),
        ]);
    }

    /**
     * Subscribe to a notification topic
     */
    public function subscribe(Request $request, string $topicId): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $topic = NotificationTopic::find($topicId);

        if (! $topic) {
            return response()->json(['message' => 'Topic not found'], 404);
        }

        if (! $topic->is_active) {
            return response()->json(['message' => 'Cannot subscribe to inactive topic'], 422);
        }

        $subscriptionId = $request->input('subscription_id');
        $subscription = PushSubscription::where('subscription_id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // Check if already subscribed
        if ($subscription->topics()->where('notification_topics.id', $topic->id)->exists()) {
            return response()->json([
                'data' => [
                    'success' => true,
                    'message' => 'Already subscribed to topic',
                ],
            ]);
        }

        // Subscribe to topic
        $subscription->topics()->attach($topic->id, [
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'success' => true,
                'message' => 'Successfully subscribed to topic',
            ],
        ]);
    }

    /**
     * Unsubscribe from a notification topic
     */
    public function unsubscribe(Request $request, string $topicId): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $topic = NotificationTopic::find($topicId);

        if (! $topic) {
            return response()->json(['message' => 'Topic not found'], 404);
        }

        $subscriptionId = $request->input('subscription_id');
        $subscription = PushSubscription::where('subscription_id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        // Unsubscribe from topic
        $subscription->topics()->detach($topic->id);

        return response()->json([
            'data' => [
                'success' => true,
                'message' => 'Successfully unsubscribed from topic',
            ],
        ]);
    }

    /**
     * List user's topic subscriptions
     */
    public function userSubscriptions(): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Get all topics with subscription status for this user
        $topics = NotificationTopic::where('is_active', true)
            ->leftJoin('notification_topic_subscriptions', function ($join) use ($user): void {
                $join->on('notification_topics.id', '=', 'notification_topic_subscriptions.notification_topic_id')
                    ->join('push_subscriptions', 'push_subscriptions.id', '=', 'notification_topic_subscriptions.push_subscription_id')
                    ->where('push_subscriptions.user_id', '=', $user->id);
            })
            ->select(
                'notification_topics.*',
                DB::raw('CASE WHEN notification_topic_subscriptions.notification_topic_id IS NOT NULL THEN true ELSE false END as subscribed'),
                'notification_topic_subscriptions.subscribed_at'
            )
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $topics->map(function ($topic): array {
                return [
                    'id' => $topic->id,
                    'name' => $topic->name,
                    'subscribed' => (bool) $topic->subscribed,
                    'subscribed_at' => $topic->subscribed_at,
                ];
            }),
        ]);
    }

    /**
     * Bulk subscribe to multiple topics
     */
    public function bulkSubscribe(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'subscription_id' => 'required|string',
            'topic_ids' => 'required|array',
            'topic_ids.*' => 'required|string|uuid',
        ]);

        $subscriptionId = $request->input('subscription_id');
        $subscription = PushSubscription::where('subscription_id', $subscriptionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        $topicIds = $request->input('topic_ids');
        $topics = NotificationTopic::whereIn('id', $topicIds)
            ->where('is_active', true)
            ->get();

        $subscribedCount = 0;

        DB::transaction(function () use ($subscription, $topics, &$subscribedCount): void {
            foreach ($topics as $topic) {
                if (! $subscription->topics()->where('notification_topics.id', $topic->id)->exists()) {
                    $subscription->topics()->attach($topic->id, [
                        'subscribed_at' => now(),
                    ]);
                    $subscribedCount++;
                }
            }
        });

        return response()->json([
            'data' => [
                'success' => true,
                'subscribed_count' => $subscribedCount,
            ],
        ]);
    }
}
