<?php

namespace App\Aggregates;

use App\Events\CreditAdded;
use App\Events\CreditAdjusted;
use App\Events\CreditConsumed;
use App\Events\CreditExpired;
use App\Models\CreditBalance;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class CreditAccountAggregate extends AggregateRoot
{
    /** @var array<string, mixed> */
    protected array $balances = [];

    /**
     * Add credit to an account (user or financer).
     */
    public function addCredit(string $ownerType, string $ownerId, string $type, int $amount, ?string $context = null): self
    {
        $this->recordThat(new CreditAdded(
            $ownerType,
            $ownerId,
            $type,
            $amount,
            $context
        ));

        return $this;
    }

    /**
     * Consume credit from an account if available.
     */
    public function consumeCredit(string $ownerType, string $ownerId, string $type, int $amount, ?string $byUserId = null, ?string $context = null): self
    {
        $balance = CreditBalance::where([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'type' => $type,
        ])->first();

        if (! $balance || ! $balance->hasEnough($amount)) {
            // Abort silently (could be changed to throw exception)
            return $this;
        }

        $this->recordThat(new CreditConsumed(
            $ownerType,
            $ownerId,
            $type,
            $amount,
            $byUserId,
            $context
        ));

        return $this;
    }

    /**
     * Expire credit from an account.
     */
    public function expireCredit(string $ownerType, string $ownerId, string $type, int $amount, ?string $context = null): self
    {
        return $this->recordThat(new CreditExpired(
            $ownerType,
            $ownerId,
            $type,
            $amount,
            $context
        ));
    }

    /**
     * Adjust credit balance directly.
     */
    public function adjustCredit(string $ownerType, string $ownerId, string $type, int $oldAmount, int $newAmount, ?string $byAdminId = null, ?string $context = null): self
    {
        return $this->recordThat(new CreditAdjusted(
            $ownerType,
            $ownerId,
            $type,
            $oldAmount,
            $newAmount,
            $byAdminId,
            $context
        ));
    }
}
