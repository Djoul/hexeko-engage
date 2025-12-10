<?php

declare(strict_types=1);

namespace App\ValueObjects\Metrics;

final class ModuleUsage
{
    /**
     * @param  array<int, array{date: string, value: float}>  $trend
     */
    public function __construct(
        public readonly string $name,
        public readonly int $uniqueUsers,
        public readonly int $totalUses,
        public readonly array $trend = []
    ) {}

    /**
     * @return array{name: string, unique_users: int, total_uses: int, trend: array<int, array{date: string, value: float}>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'unique_users' => $this->uniqueUsers,
            'total_uses' => $this->totalUses,
            'trend' => $this->trend,
        ];
    }

    /**
     * @param  array{name: string, unique_users: int, total_uses: int, trend?: array<int, array{date: string, value: float}>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            uniqueUsers: $data['unique_users'],
            totalUses: $data['total_uses'],
            trend: $data['trend'] ?? []
        );
    }

    public function getAverageUsagePerUser(): float
    {
        if ($this->uniqueUsers === 0) {
            return 0.0;
        }

        return round($this->totalUses / $this->uniqueUsers, 2);
    }

    public function getUsageIntensity(): string
    {
        $average = $this->getAverageUsagePerUser();

        return match (true) {
            $average >= 10 => 'high',
            $average >= 5 => 'medium',
            $average > 0 => 'low',
            default => 'none',
        };
    }
}
