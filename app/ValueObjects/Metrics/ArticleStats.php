<?php

declare(strict_types=1);

namespace App\ValueObjects\Metrics;

final class ArticleStats
{
    public function __construct(
        public readonly int $views,
        public readonly int $uniqueUsers
    ) {}

    /**
     * @return array{views: int, unique_users: int}
     */
    public function toArray(): array
    {
        return [
            'views' => $this->views,
            'unique_users' => $this->uniqueUsers,
        ];
    }

    /**
     * @param  array{views: int, unique_users: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            views: $data['views'],
            uniqueUsers: $data['unique_users']
        );
    }

    public function getTotalEngagement(): int
    {
        return $this->views;
    }

    public function getEngagementRate(): float
    {
        if ($this->uniqueUsers === 0) {
            return 0.0;
        }

        return round($this->views / $this->uniqueUsers, 2);
    }
}
