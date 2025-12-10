<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use Carbon\Carbon;

class ReconciliationResultDTO
{
    /**
     * @param  array<string, mixed>  $interfaces
     */
    public function __construct(
        public readonly string $runId,
        public readonly Carbon $startedAt,
        public readonly Carbon $completedAt,
        public readonly array $interfaces,
        public readonly int $totalFilesSynced,
        public readonly int $totalJobsDispatched,
        public readonly bool $success,
        public readonly ?string $error = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            runId: (string) $data['runId'],
            startedAt: Carbon::parse((string) $data['startedAt']),
            completedAt: Carbon::parse((string) $data['completedAt']),
            interfaces: (array) $data['interfaces'],
            totalFilesSynced: (int) $data['totalFilesSynced'],
            totalJobsDispatched: (int) $data['totalJobsDispatched'],
            success: (bool) $data['success'],
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'runId' => $this->runId,
            'startedAt' => $this->startedAt,
            'completedAt' => $this->completedAt,
            'interfaces' => $this->interfaces,
            'totalFilesSynced' => $this->totalFilesSynced,
            'totalJobsDispatched' => $this->totalJobsDispatched,
            'success' => $this->success,
            'error' => $this->error,
        ];
    }
}
