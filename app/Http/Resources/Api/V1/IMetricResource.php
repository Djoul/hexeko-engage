<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\DTOs\Financer\IMetricDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IMetricResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var IMetricDTO $dto */
        $dto = $this->resource;

        $data = [
            'title' => $dto->title,
            'tooltip' => $dto->tooltip,
            'value' => $dto->value,
            'labels' => $dto->labels,
            'unit' => $dto->unit,
        ];

        if ($dto->isSimpleMetric()) {
            $data['data'] = $dto->data;
        }

        if ($dto->isMultipleMetric()) {
            $data['datasets'] = $dto->datasets;
        }

        return $data;
    }
}
