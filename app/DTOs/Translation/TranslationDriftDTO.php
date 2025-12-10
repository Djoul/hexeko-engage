<?php

declare(strict_types=1);

namespace App\DTOs\Translation;

use Carbon\Carbon;

class TranslationDriftDTO
{
    /**
     * @param  array<string, mixed>  $interfaces
     */
    public function __construct(
        public readonly bool $hasDrift,
        public readonly Carbon $checkedAt,
        public readonly array $interfaces,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            hasDrift: (bool) $data['hasDrift'],
            checkedAt: Carbon::parse((string) $data['checkedAt']),
            interfaces: (array) $data['interfaces'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'hasDrift' => $this->hasDrift,
            'checkedAt' => $this->checkedAt,
            'interfaces' => $this->interfaces,
        ];
    }
}
