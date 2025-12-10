<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Http\Resources;

use App\Integrations\Wellbeing\WellWo\DTOs\WellWoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property WellWoDTO $resource
 */
class ClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'image' => $this->resource->image,
            'description' => $this->resource->description,
            'videos_count' => $this->resource->videosCount,
        ];
    }
}
