<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\IDP\RoleDefaults;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Traits\AuditableModel;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Invoice extends LoggableModel implements Auditable
{
    use AuditableModel;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected static function logName(): string
    {
        return 'invoice';
    }

    protected $casts = [
        'id' => 'string',
        'issuer_id' => 'string',
        'recipient_id' => 'string',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'subtotal_htva' => 'int',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'int',
        'total_ttc' => 'int',
        'confirmed_at' => 'datetime',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
        'due_date' => 'date',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => InvoiceStatus::DRAFT,
        'currency' => 'EUR',
        'metadata' => '[]',
    ];

    /**
     * @return HasMany<InvoiceItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope pour filtrer les factures accessibles par un utilisateur
     * en fonction de ses rôles et de ses divisions/financers accessibles
     */
    #[Scope]
    public function accessibleByUser(Builder $query, User $user): Builder
    {
        // GOD/HEXEKO : accès à toutes les factures
        if ($user->hasAnyRole([
            RoleDefaults::GOD,
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
        ])) {
            return $query;
        }

        $accessibleDivisions = authorizationContext()->divisionIds();
        $accessibleFinancers = authorizationContext()->financerIds();

        // Rôles Division : voir les factures de leurs divisions
        if ($user->hasAnyRole([
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
        ])) {
            return $query->where(function (Builder $q) use ($accessibleDivisions): void {
                // HEXEKO_TO_DIVISION : division est recipient
                $q->where(function (Builder $subQ) use ($accessibleDivisions): void {
                    $subQ->where('invoice_type', InvoiceType::HEXEKO_TO_DIVISION)
                        ->whereIn('recipient_id', $accessibleDivisions);
                })
                // DIVISION_TO_FINANCER : division est issuer
                    ->orWhere(function (Builder $subQ) use ($accessibleDivisions): void {
                        $subQ->where('invoice_type', InvoiceType::DIVISION_TO_FINANCER)
                            ->whereIn('issuer_id', $accessibleDivisions);
                    });
            });
        }

        // Rôles Financer : voir les factures DIVISION_TO_FINANCER pour leurs financers
        if ($user->hasAnyRole([
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ])) {
            return $query->where('invoice_type', InvoiceType::DIVISION_TO_FINANCER)
                ->whereIn('recipient_id', $accessibleFinancers);
        }

        // Par défaut : aucun accès
        return $query->whereRaw('1 = 0');
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    public function issuerDivision(): ?Division
    {
        if ($this->invoice_type !== InvoiceType::DIVISION_TO_FINANCER) {
            return null;
        }

        return $this->issuer_id ? Division::find($this->issuer_id) : null;
    }

    public function recipientDivision(): ?Division
    {
        if ($this->invoice_type !== InvoiceType::HEXEKO_TO_DIVISION) {
            return null;
        }

        return $this->recipient_id ? Division::find($this->recipient_id) : null;
    }
}
