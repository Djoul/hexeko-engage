<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Translation\TranslationDriftDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationDriftResource extends JsonResource
{
    public function __construct(
        private readonly TranslationDriftDTO $dto
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
            'has_drift' => $this->dto->hasDrift,
            'checked_at' => $this->dto->checkedAt->toIso8601String(),
            'interfaces' => collect($this->dto->interfaces)->map(function (array $data): array {
                return [
                    'name' => $data['name'],
                    'has_drift' => $data['has_drift'],
                    'missing_in_local' => $data['missing_in_local'] ?? [],
                    'missing_in_s3' => $data['missing_in_s3'] ?? [],
                    'checksum_mismatches' => $data['checksum_mismatches'] ?? [],
                    'total_differences' => count($data['missing_in_local'] ?? []) +
                        count($data['missing_in_s3'] ?? []) +
                        count($data['checksum_mismatches'] ?? []),
                ];
            })->toArray(),
            'summary' => [
                'total_interfaces_checked' => count($this->dto->interfaces),
                'interfaces_with_drift' => collect($this->dto->interfaces)
                    ->filter(fn ($data): mixed => $data['has_drift'])
                    ->count(),
                'total_missing_files' => collect($this->dto->interfaces)
                    ->sum(fn ($data): int => count($data['missing_in_local'] ?? []) + count($data['missing_in_s3'] ?? [])),
                'total_checksum_mismatches' => collect($this->dto->interfaces)
                    ->sum(fn ($data): int => count($data['checksum_mismatches'] ?? [])),
                'recommendation' => $this->dto->hasDrift
                    ? 'Run reconciliation to sync files'
                    : 'System is in sync',
            ],
        ];
    }
}
