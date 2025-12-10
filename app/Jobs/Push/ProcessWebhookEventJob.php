<?php

namespace App\Jobs\Push;

use App\Actions\Push\ProcessWebhookEventAction;
use App\DTOs\Push\PushEventDTO;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly PushEventDTO $eventDto
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(ProcessWebhookEventAction $action): void
    {
        try {
            Log::info('Processing webhook event job', [
                'event_type' => $this->eventDto->eventType,
                'notification_id' => $this->eventDto->pushNotificationId,
                'external_id' => $this->eventDto->externalId,
            ]);

            $event = $action->execute($this->eventDto);

            if ($event) {
                Log::info('Webhook event job completed successfully', [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'notification_id' => $event->push_notification_id,
                ]);
            } else {
                Log::warning('Webhook event job completed but no event was created', [
                    'dto' => $this->eventDto->toArray(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('Webhook event job failed', [
                'error' => $e->getMessage(),
                'event_type' => $this->eventDto->eventType,
                'notification_id' => $this->eventDto->pushNotificationId,
                'attempts' => $this->attempts(),
            ]);

            // Don't retry for validation errors
            if ($e instanceof ValidationException) {
                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Webhook event job permanently failed', [
            'event_type' => $this->eventDto->eventType,
            'notification_id' => $this->eventDto->pushNotificationId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Could optionally notify monitoring service here
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'webhook',
            'push-event',
            'event-type:'.$this->eventDto->eventType,
            'notification-id:'.$this->eventDto->pushNotificationId,
        ];
    }

    /**
     * Determine the unique ID for the job.
     */
    public function uniqueId(): string
    {
        // Prevent duplicate processing of the same webhook event
        return sprintf(
            'webhook:%s:%s:%s',
            $this->eventDto->pushNotificationId,
            $this->eventDto->eventType,
            $this->eventDto->occurredAt?->timestamp ?? time()
        );
    }

    /**
     * The unique ID may be valid for this many seconds.
     */
    public function uniqueFor(): int
    {
        return 300; // 5 minutes
    }
}
