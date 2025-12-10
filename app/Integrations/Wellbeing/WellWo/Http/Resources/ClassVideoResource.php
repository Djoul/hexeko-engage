<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Http\Resources;

use App\Integrations\Wellbeing\WellWo\DTOs\ClassVideoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ClassVideoDTO $resource
 */
class ClassVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'name' => $resource['name'] ?? null,
            'description' => $resource['description'] ?? null,
            'url' => $resource['url'] ?? null,
            'level' => $resource['level'] ?? null,
            'image' => $resource['image'] ?? null,
        ];
    }
}
