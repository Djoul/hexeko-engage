<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Translation\TranslationHealthDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationHealthResource extends JsonResource
{
    public function __construct(
        private readonly TranslationHealthDTO $dto
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
            'healthy' => $this->dto->healthy,
            'checked_at' => $this->dto->checkedAt->toIso8601String(),
            'interfaces' => collect($this->dto->interfaces)->map(function (array $data): array {
                return [
                    'name' => $data['name'],
                    'status' => $data['status'],
                    'local_files' => $data['local_files'],
                    's3_files' => $data['s3_files'],
                    'pending_migrations' => $data['pending_migrations'],
                    'last_sync' => $data['last_sync']?->toIso8601String(),
                    'manifest_valid' => $data['manifest_valid'],
                    'issues' => $data['issues'] ?? [],
                ];
            })->toArray(),
            'summary' => [
                'total_interfaces' => count($this->dto->interfaces),
                'healthy_interfaces' => collect($this->dto->interfaces)
                    ->filter(fn ($data): bool => $data['status'] === 'healthy')
                    ->count(),
                'total_pending_migrations' => collect($this->dto->interfaces)
                    ->sum('pending_migrations'),
                'environment' => app()->environment(),
                'auto_sync_enabled' => config('translations.auto_sync_enabled'),
                'reconciliation_enabled' => config('translations.reconciliation.enabled'),
                'manifest_required' => config('translations.manifest_required'),
            ],
        ];
    }
}
