<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use Carbon\Carbon;

class TranslationHealthDTO
{
    /**
     * @param  array<string, mixed>  $interfaces
     */
    public function __construct(
        public readonly bool $healthy,
        public readonly Carbon $checkedAt,
        public readonly array $interfaces,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            healthy: is_bool($data['healthy']) ? $data['healthy'] : (bool) $data['healthy'],
            checkedAt: $data['checkedAt'] instanceof Carbon ? $data['checkedAt'] : Carbon::parse($data['checkedAt']),
            interfaces: is_array($data['interfaces']) ? $data['interfaces'] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'healthy' => $this->healthy,
            'checkedAt' => $this->checkedAt,
            'interfaces' => $this->interfaces,
        ];
    }
}
