<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\DTOs\Financer\FinancerMetricsDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancerMetricsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var FinancerMetricsDTO $dto */
        $dto = $this->resource;

        $data = [
            'financer_id' => $dto->financer_id,
        ];

        // Add fields that are present
        if ($dto->period !== null) {
            $data['period'] = $dto->period;
        }

        if ($dto->interval !== null) {
            $data['interval'] = $dto->interval;
        }

        if ($dto->total !== null) {
            $data['total'] = $dto->total;
        }

        if ($dto->rate !== null) {
            $data['rate'] = $dto->rate;
        }

        if ($dto->total_users !== null) {
            $data['total_users'] = $dto->total_users;
        }

        if ($dto->activated_users !== null) {
            $data['activated_users'] = $dto->activated_users;
        }

        if ($dto->median_minutes !== null) {
            $data['median_minutes'] = $dto->median_minutes;
        }

        if ($dto->total_sessions !== null) {
            $data['total_sessions'] = $dto->total_sessions;
        }

        if ($dto->total_interactions !== null) {
            $data['total_interactions'] = $dto->total_interactions;
        }

        if ($dto->metrics !== null) {
            $data['metrics'] = $dto->metrics;
        }

        if ($dto->data !== null) {
            $data['data'] = $dto->data;
        }

        if ($dto->trend !== null) {
            $data['trend'] = $dto->trend;
        }

        if ($dto->articles !== null) {
            $data['articles'] = $dto->articles;
        }

        if ($dto->tools !== null) {
            $data['tools'] = $dto->tools;
        }

        if ($dto->modules !== null) {
            $data['modules'] = $dto->modules;
        }

        if ($dto->cache_info !== null) {
            $data['cache_info'] = $dto->cache_info;
        }

        return $data;
    }
}
