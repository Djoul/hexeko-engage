<?php

declare(strict_types=1);

namespace App\ValueObjects\Metrics;

use Carbon\Carbon;
use InvalidArgumentException;

final class DateRange
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end
    ) {
        if ($start->isAfter($end)) {
            throw new InvalidArgumentException('Start date must be before end date');
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function toArray(): array
    {
        return [$this->start, $this->end];
    }

    public static function fromPeriod(string $period): self
    {
        $endDate = Carbon::now();

        $startDate = match ($period) {
            '7_days' => $endDate->copy()->subDays(7),
            '30_days' => $endDate->copy()->subDays(30),
            '3_months' => $endDate->copy()->subMonths(3),
            '6_months' => $endDate->copy()->subMonths(6),
            '12_months' => $endDate->copy()->subMonths(12),
            default => $endDate->copy()->subDays(7),
        };

        return new self($startDate, $endDate);
    }

    public function getDurationInDays(): int
    {
        return (int) $this->start->diffInDays($this->end);
    }

    public function contains(Carbon $date): bool
    {
        return $date->between($this->start, $this->end);
    }
}
