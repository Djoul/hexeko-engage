<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CsvInvitedUsersImportCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $financerId,
        public readonly string $userId,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $failedRows,
        public readonly string $status = 'completed',
        public readonly ?string $error = null,
        /** @var array<int, array<string, mixed>> */
        public readonly array $failedRowsDetails = [],
        public readonly ?float $totalDuration = null,
        public readonly ?string $startedAt = null,
        public readonly ?string $completedAt = null
    ) {
        // Only log when not in unit tests
        if (! app()->runningUnitTests()) {
            Log::warning('[WebSocket] CsvInvitedUsersImportCompleted event created', [
                'import_id' => $this->importId,
                'user_id' => $this->userId,
                'processed_rows' => $this->processedRows,
                'failed_rows' => $this->failedRows,
                'status' => $this->status,
                'channel' => 'private-user.'.$this->userId,
            ]);
        }
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'import.completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Limit failed rows details to prevent payload overflow
        $maxErrorDetails = 10;
        $limitedFailedRowsDetails = array_slice($this->failedRowsDetails, 0, $maxErrorDetails);

        // Truncate long error messages
        $limitedFailedRowsDetails = array_map(function (array $detail): array {
            if (array_key_exists('error', $detail) && is_string($detail['error']) && strlen($detail['error']) > 200) {
                $detail['error'] = substr($detail['error'], 0, 200).'...';
            }

            return $detail;
        }, $limitedFailedRowsDetails);

        // Create error summary by grouping errors
        $errorSummary = [];
        foreach ($this->failedRowsDetails as $detail) {
            $errorMessage = $detail['error'] ?? 'Unknown error';
            if (is_string($errorMessage) && ! array_key_exists($errorMessage, $errorSummary)) {
                $errorSummary[$errorMessage] = 0;
            }
            $errorSummary[$errorMessage]++;
        }

        $data = [
            'import_id' => $this->importId,
            'financer_id' => $this->financerId,
            'total_rows' => $this->totalRows,
            'processed_rows' => $this->processedRows,
            'failed_rows' => $this->failedRows,
            'failed_rows_details' => $limitedFailedRowsDetails,
            'has_more_errors' => count($this->failedRowsDetails) > $maxErrorDetails,
            'total_errors' => count($this->failedRowsDetails),
            'error_summary' => $errorSummary,
            'status' => $this->status,
            'error' => $this->error,
            'total_duration_seconds' => $this->totalDuration,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt ?? now()->toIso8601String(),
        ];

        // Only log when not in unit tests
        if (! app()->runningUnitTests()) {
            Log::warning('[WebSocket] Broadcasting ImportCompleted event', [
                'event' => 'import.completed',
                'channel' => 'private-user.'.$this->userId,
                'data_size' => strlen((string) json_encode($data)),
                'total_errors' => count($this->failedRowsDetails),
                'errors_included' => count($limitedFailedRowsDetails),
            ]);
        }

        return $data;
    }
}
