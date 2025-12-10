<?php

namespace App\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CreditConsumed extends ShouldBeStored
{
    public function __construct(
        public string $ownerType,
        public string $ownerId,
        public string $type,
        public int $amount,
        public ?string $byUserId = null, // facultatif : ID utilisateur initiateur
        public ?string $context = null
    ) {}
}
