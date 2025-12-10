<?php

namespace App\Events;

use DateTimeInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApideckSyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DateTimeInterface $timestamp;

    /**
     * Event type for frontend interpretation
     */
    public string $type;

    /**
     * Event severity level
     */
    public string $severity;

    /**
     * Create a new event instance.
     *
     * @param  string  $financerId  The financer ID for which the sync was performed
     * @param  array<string, mixed>  $syncData  The sync results data
     */
    public function __construct(
        public string $financerId,
        public array $syncData
    ) {
        $this->timestamp = now();
        $this->determineEventType();
        $this->determineSeverity();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('financer.'.$this->financerId),
        ];

        // If sync was for a specific division, also broadcast to division channel
        if (array_key_exists('division_id', $this->syncData)) {
            $divisionId = is_scalar($this->syncData['division_id']) ? (string) $this->syncData['division_id'] : '';
            $channels[] = new PrivateChannel('division.'.$divisionId);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'apideck.sync.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'severity' => $this->severity,
            'financer_id' => $this->financerId,
            'sync_data' => $this->syncData,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * Determine the event type based on sync results
     */
    private function determineEventType(): void
    {
        if (array_key_exists('error', $this->syncData)) {
            $this->type = 'sync.error';
        } elseif (($this->syncData['failed'] ?? 0) > 0) {
            $this->type = 'sync.partial';
        } elseif (($this->syncData['created'] ?? 0) === 0 && ($this->syncData['updated'] ?? 0) === 0) {
            $this->type = 'sync.no_changes';
        } else {
            $this->type = 'sync.success';
        }
    }

    /**
     * Determine the severity level
     */
    private function determineSeverity(): void
    {
        $this->severity = match ($this->type) {
            'sync.error' => 'error',
            'sync.partial' => 'warning',
            'sync.no_changes' => 'info',
            'sync.success' => 'success',
            default => 'info',
        };
    }
}
