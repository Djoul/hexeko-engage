<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\DTOs\Financer\AllMetricsDTO;
use App\Enums\FinancerMetricType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AllMetricsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AllMetricsDTO $dto */
        $dto = $this->resource;

        return [
            FinancerMetricType::ACTIVE_BENEFICIARIES => new IMetricResource($dto->active_beneficiaries),
            FinancerMetricType::ACTIVATION_RATE => new IMetricResource($dto->activation_rate),
            FinancerMetricType::SESSION_TIME => new IMetricResource($dto->average_session_time),
            FinancerMetricType::ARTICLE_VIEWED => new IMetricResource($dto->article_viewed_views),
        ];
    }
}
