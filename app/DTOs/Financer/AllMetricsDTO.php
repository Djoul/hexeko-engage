<?php

declare(strict_types=1);

namespace App\DTOs\Financer;

class AllMetricsDTO
{
    public function __construct(
        public IMetricDTO $active_beneficiaries,
        public IMetricDTO $activation_rate,
        public IMetricDTO $average_session_time,
        public IMetricDTO $article_viewed_views,
    ) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        return [
            'active_beneficiaries' => $this->active_beneficiaries->toArray(),
            'activation_rate' => $this->activation_rate->toArray(),
            'average_session_time' => $this->average_session_time->toArray(),
            'article_viewed_views' => $this->article_viewed_views->toArray(),
        ];
    }
}
