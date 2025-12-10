<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TranslationMigration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranslationMigrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TranslationMigration $resource */
        $resource = $this->resource;

        return [
            'id' => $resource->id,
            'interface_origin' => $resource->interface_origin,
            'version' => $resource->version,
            'filename' => $resource->filename,
            'status' => $resource->status,
            'checksum' => $resource->checksum,
            'executed_at' => $resource->executed_at?->toISOString(),
            'rolled_back_at' => $resource->rolled_back_at?->toISOString(),
            'metadata' => $resource->metadata,
            'created_at' => $resource->created_at?->toISOString(),
            'updated_at' => $resource->updated_at?->toISOString(),
        ];
    }
}
