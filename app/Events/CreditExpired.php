<?php

namespace App\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CreditExpired extends ShouldBeStored
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $type,
        public int $amount, // amount expired
        public ?string $context = null // optional context (ex: batch ID)
    ) {}
}
