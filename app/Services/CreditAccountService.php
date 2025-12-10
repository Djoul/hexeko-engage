<?php

namespace App\Services;

use App\Aggregates\CreditAccountAggregate;
use Ramsey\Uuid\Uuid;

class CreditAccountService
{
    /**
     * Add credit to an account.
     */
    public static function addCredit(string $ownerType, string $ownerId, string $type, int $amount, ?string $context = null): void
    {
        CreditAccountAggregate::retrieve(static::uuidFor($ownerType, $ownerId))
            ->addCredit($ownerType, $ownerId, $type, $amount, $context)
            ->persist();
    }

    /**
     * Consume credit from an account.
     */
    public static function consumeCredit(string $ownerType, string $ownerId, string $type, int $amount, ?string $byUserId = null, ?string $context = null): void
    {
        CreditAccountAggregate::retrieve(static::uuidFor($ownerType, $ownerId))
            ->consumeCredit($ownerType, $ownerId, $type, $amount, $byUserId, $context)
            ->persist();
    }

    /**
     * Generate a UUID for the credit account aggregate.
     */
    protected static function uuidFor(string $ownerType, string $ownerId): string
    {
        // Generates a unique aggregate UUID per (owner_type, owner_id)
        return Uuid::uuid5(Uuid::NAMESPACE_DNS, "{$ownerType}:{$ownerId}")->toString();
    }
}
