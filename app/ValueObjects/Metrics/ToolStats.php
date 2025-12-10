<?php

declare(strict_types=1);

namespace App\ValueObjects\Metrics;

final class ToolStats
{
    public function __construct(
        public readonly int $clicks,
        public readonly int $uniqueUsers
    ) {}

    /**
     * @return array{clicks: int, unique_users: int}
     */
    public function toArray(): array
    {
        return [
            'clicks' => $this->clicks,
            'unique_users' => $this->uniqueUsers,
        ];
    }

    /**
     * @param  array{clicks: int, unique_users: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            clicks: $data['clicks'],
            uniqueUsers: $data['unique_users']
        );
    }

    public function getAverageClicksPerUser(): float
    {
        if ($this->uniqueUsers === 0) {
            return 0.0;
        }

        return round($this->clicks / $this->uniqueUsers, 2);
    }
}
