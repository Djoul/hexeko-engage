<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceItemType;
use App\Traits\AuditableModel;
use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class InvoiceItem extends LoggableModel
{
    use AuditableModel;
    use HasFactory;
    use HasTranslations;
    use HasUuids;

    /**
     * @var array<int, string>
     */
    public array $translatable = ['label', 'description'];

    protected static function logName(): string
    {
        return 'invoice_item';
    }

    protected $casts = [
        'id' => 'string',
        'invoice_id' => 'string',
        'module_id' => 'string',
        'label' => 'array',
        'description' => 'array',
        'beneficiaries_count' => 'int',
        'unit_price_htva' => 'int',
        'quantity' => 'int',
        'subtotal_htva' => 'int',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'int',
        'total_ttc' => 'int',
        'prorata_percentage' => 'decimal:2',
        'prorata_days' => 'int',
        'total_days' => 'int',
        'metadata' => 'array',
    ];

    /**
     * @return array<string, string|null>
     */
    public function getLabelAttribute(mixed $value): array
    {
        $translations = $this->getTranslations('label');

        if ($translations !== []) {
            return $translations;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return [config('app.locale') => $value];
        }

        return [];
    }

    /**
     * @return array<string, string|null>|null
     */
    public function getDescriptionAttribute(mixed $value): ?array
    {
        $translations = $this->getTranslations('description');

        if ($translations !== []) {
            return $translations;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return [config('app.locale') => $value];
        }

        return null;
    }

    protected $attributes = [
        'item_type' => InvoiceItemType::MODULE,
        'metadata' => '[]',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    protected static function newFactory(): InvoiceItemFactory
    {
        return InvoiceItemFactory::new();
    }
}
