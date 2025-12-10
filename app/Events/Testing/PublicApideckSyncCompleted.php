<?php

namespace App\Events\Testing;

use DateTimeInterface;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Version publique de ApideckSyncCompleted pour les tests
 */
class PublicApideckSyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DateTimeInterface $timestamp;

    public string $type;

    public string $severity;

    /**
     * @param  array<string, mixed>  $syncData
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
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('public-notifications'),
            new Channel('apideck-sync'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'apideck.sync.completed';
    }

    /**
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
