<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Http\Resources;

use App\Integrations\Wellbeing\WellWo\DTOs\VideoDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VideoDTO $resource
 */
class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resource = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'id' => $resource['id'] ?? null,
            'name' => $resource['name'] ?? null,
            'image' => $resource['image'] ?? null,
            'video' => $resource['video'] ?? null,
            'length' => $resource['length'] ?? null,
        ];
    }
}
