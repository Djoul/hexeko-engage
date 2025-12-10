<?php

namespace App\Models;

use App\Traits\AuditableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable;

class CreditBalance extends Model implements Auditable
{
    use AuditableModel;
    use HasFactory;

    protected $casts = [
        'context' => 'array',
    ];

    /**
     * Relation polymorphe vers User ou Financer.
     *
     * @phpstan-ignore-next-line
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Ajoute des crédits au solde.
     */
    public function add(int $amount): void
    {
        $this->increment('balance', $amount);
    }

    /**
     * Retire des crédits du solde.
     */
    public function subtract(int $amount): void
    {
        $this->decrement('balance', $amount);
    }

    /**
     * Vérifie si le solde est suffisant.
     */
    public function hasEnough(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Met à jour le contexte du solde de crédit.
     * Écrase complètement la valeur précédente.
     *
     * @param  array<string, mixed>  $context  Le nouveau contexte à enregistrer
     */
    public function updateContext(array $context): void
    {
        $this->update(['context' => $context]);
    }

    public function getDivisionAttribute(): ?Division
    {
        $owner = $this->owner;

        if ($owner instanceof Financer) {
            return $owner->division;
        }

        if ($owner instanceof User) {
            return $owner->financers()->first()?->division;
        }

        return null;
    }
}
