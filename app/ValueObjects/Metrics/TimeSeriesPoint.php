<?php

declare(strict_types=1);

namespace App\ValueObjects\Metrics;

final class TimeSeriesPoint
{
    public function __construct(
        public readonly string $date,
        public readonly int|float $value
    ) {}

    /**
     * @return array{date: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'count' => (int) $this->value,
        ];
    }

    /**
     * @param  array{date: string, count: int|float}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: $data['date'],
            value: $data['count']
        );
    }
}
