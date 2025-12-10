<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Translation\ReconciliationResultDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReconciliationResultResource extends JsonResource
{
    public function __construct(
        private readonly ReconciliationResultDTO $dto
    ) {
        parent::__construct($dto);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->dto->success,
            'run_id' => $this->dto->runId,
            'started_at' => $this->dto->startedAt->toIso8601String(),
            'completed_at' => $this->dto->completedAt->toIso8601String(),
            'duration_seconds' => $this->dto->completedAt->diffInSeconds($this->dto->startedAt),
            'interfaces' => collect($this->dto->interfaces)->map(function (array $data, $interface): array {
                return [
                    'interface' => $interface,
                    'files_synced' => $data['files_synced'],
                    'jobs_dispatched' => $data['jobs_dispatched'],
                    'status' => $data['jobs_dispatched'] > 0 ? 'synced' : 'no_changes',
                ];
            })->values()->toArray(),
            'summary' => [
                'total_files_synced' => $this->dto->totalFilesSynced,
                'total_jobs_dispatched' => $this->dto->totalJobsDispatched,
                'interfaces_processed' => count($this->dto->interfaces),
                'environment' => app()->environment(),
                'triggered_by' => 'manual', // Can be enhanced to track trigger source
            ],
            'error' => $this->dto->error,
        ];
    }
}
