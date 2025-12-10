<?php

namespace App\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CreditAdjusted extends ShouldBeStored
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $type,
        public int $oldAmount,
        public int $newAmount,
        public ?string $byAdminId = null,
        public ?string $context = null
    ) {}
}
