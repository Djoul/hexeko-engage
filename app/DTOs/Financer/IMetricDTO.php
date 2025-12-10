<?php

declare(strict_types=1);

namespace App\DTOs\Financer;

use Symfony\Contracts\Service\Attribute\Required;

class IMetricDTO
{
    /**
     * @param  array<string, mixed>  $labels
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $datasets
     */
    public function __construct(
        #[Required]
        public string $title,

        #[Required]
        public string $tooltip,

        #[Required]
        public int|float|string $value,

        #[Required]
        public array $labels,

        public ?string $unit = null,

        public ?array $data = null,

        public ?array $datasets = null,
    ) {}

    /**
     * @param  array<string, mixed>  $labels
     * @param  array<string, mixed>  $data
     */
    public static function createSimple(
        string $title,
        string $tooltip,
        int|float|string $value,
        array $labels,
        array $data,
        ?string $unit = null
    ): self {
        return new self(
            title: $title,
            tooltip: $tooltip,
            value: $value,
            labels: $labels,
            unit: $unit,
            data: $data,
            datasets: null
        );
    }

    /**
     * @param  array<string, mixed>  $labels
     * @param  array<string, mixed>  $datasets
     */
    public static function createMultiple(
        string $title,
        string $tooltip,
        int|float $value,
        array $labels,
        array $datasets,
        ?string $unit = null
    ): self {
        return new self(
            title: $title,
            tooltip: $tooltip,
            value: $value,
            labels: $labels,
            unit: $unit,
            data: null,
            datasets: $datasets
        );
    }

    public function isSimpleMetric(): bool
    {
        return $this->data !== null && $this->datasets === null;
    }

    public function isMultipleMetric(): bool
    {
        return $this->datasets !== null && $this->data === null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'tooltip' => $this->tooltip,
            'value' => $this->value,
            'labels' => $this->labels,
            'unit' => $this->unit,
            'data' => $this->data,
            'datasets' => $this->datasets,
        ];
    }
}
