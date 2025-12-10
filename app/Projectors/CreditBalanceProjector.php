<?php

namespace App\Projectors;

use App\Events\CreditAdded;
use App\Events\CreditAdjusted;
use App\Events\CreditConsumed;
use App\Events\CreditExpired;
use App\Models\CreditBalance;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class CreditBalanceProjector extends Projector
{
    public function onCreditAdded(CreditAdded $event): void
    {
        $balance = CreditBalance::firstOrCreate([
            'owner_type' => $event->ownerType,
            'owner_id' => $event->ownerId,
            'type' => $event->type,
        ]);

        $balance->add($event->amount);

        $balance->updateContext([
            'event' => 'credit_added',
            'reason' => $event->context,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function onCreditConsumed(CreditConsumed $event): void
    {
        $balance = CreditBalance::firstOrNew([
            'owner_type' => $event->ownerType,
            'owner_id' => $event->ownerId,
            'type' => $event->type,
        ]);

        if ($balance->exists && $balance->hasEnough($event->amount)) {
            $balance->subtract($event->amount);

            $balance->updateContext([
                'event' => 'credit_consumed',
                'by_user' => $event->byUserId,
                'reason' => $event->context,
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    public function onCreditExpired(CreditExpired $event): void
    {
        $balance = CreditBalance::where([
            'owner_type' => $event->ownerType,
            'owner_id' => $event->ownerId,
            'type' => $event->type,
        ])->first();

        if ($balance && $balance->hasEnough($event->amount)) {
            $balance->subtract($event->amount);

            $balance->updateContext([
                'event' => 'credit_expired',
                'reason' => $event->context,
                'timestamp' => now()->toISOString(),
            ]);
        }
    }

    public function onCreditAdjusted(CreditAdjusted $event): void
    {
        $balance = CreditBalance::firstOrCreate([
            'owner_type' => $event->ownerType,
            'owner_id' => $event->ownerId,
            'type' => $event->type,
        ]);

        $balance->update([
            'balance' => $event->newAmount,
        ]);

        $balance->updateContext([
            'event' => 'credit_adjusted',
            'by_user' => $event->byAdminId,
            'reason' => $event->context,
            'old_amount' => $event->oldAmount,
            'new_amount' => $event->newAmount,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
