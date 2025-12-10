<?php

namespace App\Services;

use Berkayk\OneSignal\OneSignalFacade as OneSignal;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OneSignalService
{
    /**
     * Send notification to specific users
     */
    public function sendToUsers(array $notification, array $deviceIds): array
    {
        try {
            $notification['include_player_ids'] = $deviceIds;

            $response = OneSignal::sendNotificationToUser(
                $notification['contents']['en'],
                $deviceIds,
                $notification['headings']['en'] ?? null,
                $notification['data'] ?? null,
                $notification['buttons'] ?? null,
                $notification['schedule'] ?? null,
                $notification['url'] ?? null,
                $notification['chrome_web_icon'] ?? null,
                $notification['chrome_web_image'] ?? null,
                $notification['chrome_web_badge'] ?? null,
                $notification['firefox_icon'] ?? null,
                $notification['chrome_icon'] ?? null,
                $notification['ios_sound'] ?? null,
                $notification['android_sound'] ?? null,
                $notification['android_led_color'] ?? null,
                $notification['android_accent_color'] ?? null,
                $notification['android_visibility'] ?? null,
                $notification['ios_badge_type'] ?? null,
                $notification['ios_badge_count'] ?? null,
                $notification['collapse_id'] ?? null,
                $notification['send_after'] ?? null,
                $notification['delayed_option'] ?? null,
                $notification['delivery_time_of_day'] ?? null,
                $notification['ttl'] ?? null,
                $notification['priority'] ?? null,
                $notification['android_group'] ?? null,
                $notification['android_group_message'] ?? null,
                $notification['adm_group'] ?? null,
                $notification['adm_group_message'] ?? null,
                $notification['thread_id'] ?? null,
                $notification['summary_arg'] ?? null,
                $notification['summary_arg_count'] ?? null,
                $notification['email_subject'] ?? null,
                $notification['email_body'] ?? null,
                $notification['email_from_name'] ?? null,
                $notification['email_from_address'] ?? null
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('OneSignal send to users failed', [
                'error' => $e->getMessage(),
                'device_ids' => $deviceIds,
            ]);

            throw $e;
        }
    }

    /**
     * Broadcast notification to all users
     */
    public function broadcast(array $notification): array
    {
        try {
            // Use direct API call instead of package (package has bug with sendNotificationToAll)
            $client = new Client;

            $payload = [
                'app_id' => config('onesignal.app_id'),
                'headings' => $notification['headings'],
                'contents' => $notification['contents'],
                'included_segments' => ['All'],
            ];

            // Add optional fields
            if (isset($notification['data'])) {
                $payload['data'] = $notification['data'];
            }

            if (isset($notification['url'])) {
                $payload['url'] = $notification['url'];
            }

            if (isset($notification['buttons'])) {
                $payload['buttons'] = $notification['buttons'];
            }

            if (isset($notification['big_picture'])) {
                $payload['big_picture'] = $notification['big_picture'];
            }

            if (isset($notification['chrome_web_image'])) {
                $payload['chrome_web_image'] = $notification['chrome_web_image'];
            }

            if (isset($notification['chrome_web_icon'])) {
                $payload['chrome_web_icon'] = $notification['chrome_web_icon'];
            }

            if (isset($notification['firefox_icon'])) {
                $payload['firefox_icon'] = $notification['firefox_icon'];
            }

            if (isset($notification['priority'])) {
                $payload['priority'] = $notification['priority'];
            }

            if (isset($notification['ttl'])) {
                $payload['ttl'] = $notification['ttl'];
            }

            $response = $client->post(config('onesignal.rest_api_url').'/notifications', [
                'headers' => [
                    'Authorization' => 'Basic '.config('onesignal.rest_api_key'),
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('OneSignal broadcast failed', [
                'error' => $e->getMessage(),
                'notification' => $notification,
            ]);

            throw $e;
        }
    }

    /**
     * Send notification using segments
     */
    public function sendToSegments(array $notification, array $segments): array
    {
        try {
            $notification['included_segments'] = $segments;

            $response = OneSignal::sendNotificationToSegment(
                $notification['contents']['en'],
                $segments,
                $notification['headings']['en'] ?? null,
                $notification['data'] ?? null,
                $notification['buttons'] ?? null,
                $notification['schedule'] ?? null,
                $notification['url'] ?? null
            );

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('OneSignal send to segments failed', [
                'error' => $e->getMessage(),
                'segments' => $segments,
            ]);

            throw $e;
        }
    }

    /**
     * Cancel a scheduled notification
     */
    public function cancelNotification(string $notificationId): bool
    {
        try {
            $response = OneSignal::cancelNotification($notificationId);

            return $response->getStatusCode() === 200;
        } catch (Exception $e) {
            Log::error('OneSignal cancel notification failed', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId,
            ]);

            return false;
        }
    }

    /**
     * Get notification details
     */
    public function getNotification(string $notificationId): ?array
    {
        try {
            $response = OneSignal::getNotification($notificationId);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('OneSignal get notification failed', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId,
            ]);

            return null;
        }
    }

    /**
     * Get notification history
     */
    public function getNotificationHistory(int $limit = 50, int $offset = 0): array
    {
        try {
            $response = OneSignal::getNotifications($limit, $offset);

            return json_decode($response->getBody()->getContents(), true);
        } catch (Exception $e) {
            Log::error('OneSignal get notification history failed', [
                'error' => $e->getMessage(),
            ]);

            return ['notifications' => []];
        }
    }
}
