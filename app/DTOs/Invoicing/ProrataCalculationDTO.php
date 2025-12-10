<?php

declare(strict_types=1);

namespace App\DTOs\Invoicing;

class ProrataCalculationDTO
{
    public function __construct(
        public readonly float $percentage,
        public readonly int $days,
        public readonly int $totalDays,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly ?string $activationDate = null,
        public readonly ?string $deactivationDate = null,
    ) {}

    /**
     * @return array<string, int|float|string|null>
     */
    public function toArray(): array
    {
        return [
            'percentage' => $this->percentage,
            'days' => $this->days,
            'total_days' => $this->totalDays,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'activation_date' => $this->activationDate,
            'deactivation_date' => $this->deactivationDate,
        ];
    }
}
