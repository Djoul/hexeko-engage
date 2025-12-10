<?php

namespace App\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class CreditAdded extends ShouldBeStored
{
    public function __construct(
        public string $ownerType, // 'user' ou 'financer'
        public string $ownerId,
        public string $type,      // ai_token, sms, etc.
        public int $amount,
        public ?string $context = null // facultatif : raison, ID lié, etc.
    ) {}
}
