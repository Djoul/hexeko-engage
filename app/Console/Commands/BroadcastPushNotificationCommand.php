<?php

namespace App\Console\Commands;

use App\Actions\Push\SendPushNotificationAction;
use App\DTOs\Push\PushNotificationDTO;
use App\Enums\NotificationDeliveryTypes;
use App\Enums\NotificationTypes;
use App\Jobs\Push\SendPushNotificationJob;
use App\Models\PushNotification;
use Exception;
use Illuminate\Console\Command;

class BroadcastPushNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:broadcast
                            {title : The notification title}
                            {message : The notification message}
                            {--data= : Additional data as JSON}
                            {--segments=* : Target segments (comma-separated)}
                            {--icon= : Notification icon URL}
                            {--url= : Action URL when notification is clicked}
                            {--sync : Send immediately without queuing}
                            {--dry-run : Preview the notification without sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Broadcast a push notification to all users or specific segments';

    /**
     * Execute the console command.
     */
    public function handle(SendPushNotificationAction $action): int
    {
        $title = $this->argument('title');
        $message = $this->argument('message');
        $dataJson = $this->option('data');
        $segments = $this->option('segments');
        $icon = $this->option('icon');
        $url = $this->option('url');
        $queueConfig = config('queue.connections.sqs.queue');
        $queue = is_string($queueConfig) ? $queueConfig : 'default';
        $sync = $this->option('sync');
        $dryRun = $this->option('dry-run');

        // Parse data JSON if provided
        $data = null;
        if ($dataJson) {
            $data = json_decode($dataJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON format for data parameter');

                return 1;
            }
        }

        // Parse segments
        $segmentsList = [];
        if (! empty($segments)) {
            $segmentsList = is_array($segments) ? $segments : array_map('trim', explode(',', $segments));
        }

        // Determine delivery type
        $deliveryType = $segmentsList === []
            ? NotificationDeliveryTypes::BROADCAST()
            : NotificationDeliveryTypes::SEGMENT();

        // Handle dry run
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No notification will be sent');
            $this->newLine();
            $this->line('Title: '.$title);
            $this->line('Message: '.$message);
            $this->line('Delivery Type: '.$deliveryType);

            if ($segmentsList !== []) {
                $this->line('Segments: '.implode(', ', $segmentsList));
            }

            if ($data) {
                $this->line('Data: '.json_encode($data, JSON_PRETTY_PRINT));
            }

            if ($icon) {
                $this->line('Icon: '.$icon);
            }

            if ($url) {
                $this->line('URL: '.$url);
            }

            $this->line('Queue: '.$queue);
            $this->line('Sync: '.($sync ? 'Yes' : 'No'));

            return 0;
        }

        // Create notification model
        $notification = new PushNotification([
            'title' => $title,
            'body' => $message,
            'data' => $data,
            'icon' => $icon,
            'url' => $url,
            'delivery_type' => $deliveryType->value,
            'type' => NotificationTypes::SYSTEM(),
            'status' => 'pending',
        ]);

        // Add segments to data if segment delivery
        if ($deliveryType->is(NotificationDeliveryTypes::SEGMENT)) {
            $notification->data = array_merge($notification->data ?? [], [
                'segments' => $segmentsList,
            ]);
        }

        // Send notification
        if ($sync) {
            $this->info('Broadcasting push notification...');

            try {
                $notificationType = $notification->type instanceof NotificationTypes
                    ? $notification->type
                    : NotificationTypes::SYSTEM();

                $dto = new PushNotificationDTO(
                    title: $notification->title,
                    body: $notification->body,
                    type: $notificationType, // Empty array = broadcast to all
                    url: $notification->url,
                    icon: $notification->icon,
                    data: $notification->data ?? [],
                    recipientIds: [],
                );

                $result = $action->execute($dto);

                $recipients = $result->recipient_count ?? 0;
                $this->info("✓ Push notification sent to {$recipients} recipients");
                $this->line("Status: {$result->status}");
                if ($result->external_id) {
                    $this->line("OneSignal ID: {$result->external_id}");
                }

                return 0;
            } catch (Exception $e) {
                $this->error('✗ Failed to send push notification: '.$e->getMessage());

                return 1;
            }
        }

        // Queue the notification
        SendPushNotificationJob::dispatch($notification)
            ->onQueue($queue);

        $this->info("✓ Push notification queued for delivery on '{$queue}' queue");

        return 0;
    }
}
