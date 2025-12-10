<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceItemType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = Invoice::factory();
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->numberBetween(500, 5_000);
        $subtotal = $unitPrice * $quantity;
        $vatAmount = (int) round($subtotal * 0.21);
        $total = $subtotal + $vatAmount;

        return [
            'invoice_id' => $invoice,
            'item_type' => InvoiceItemType::MODULE,
            'module_id' => null,
            'label' => [
                'fr' => 'Prestation',
                'en' => 'Service',
            ],
            'description' => [
                'fr' => 'Prestation de service standard',
                'en' => 'Standard service fee',
            ],
            'beneficiaries_count' => $this->faker->numberBetween(5, 50),
            'unit_price_htva' => $unitPrice,
            'quantity' => $quantity,
            'subtotal_htva' => $subtotal,
            'vat_rate' => '21.00',
            'vat_amount' => $vatAmount,
            'total_ttc' => $total,
            'prorata_percentage' => '100.00',
            'prorata_days' => 30,
            'total_days' => 30,
            'metadata' => [
                'generated_at' => Date::now()->toDateString(),
            ],
        ];
    }

    public function corePackage(): self
    {
        return $this->state(fn (): array => [
            'item_type' => InvoiceItemType::CORE_PACKAGE,
        ]);
    }
}
