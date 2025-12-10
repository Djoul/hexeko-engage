<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Date::now()->copy()->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $subtotal = $this->faker->numberBetween(5_000, 50_000);
        $vatRate = '21.00';
        $vatAmount = (int) round($subtotal * 0.21);
        $total = $subtotal + $vatAmount;

        return [
            'invoice_number' => 'INV-'.Str::uuid()->toString(),
            'invoice_type' => $this->faker->randomElement(['hexeko_to_division', 'division_to_financer']),
            'issuer_type' => Financer::class,
            'issuer_id' => Financer::factory(),
            'recipient_type' => Division::class,
            'recipient_id' => Division::factory(),
            'billing_period_start' => $start->toDateString(),
            'billing_period_end' => $end->toDateString(),
            'subtotal_htva' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total_ttc' => $total,
            'currency' => 'EUR',
            'status' => InvoiceStatus::DRAFT,
            'confirmed_at' => null,
            'sent_at' => null,
            'paid_at' => null,
            'due_date' => $end->copy()->addDays(15)->toDateString(),
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }

    public function confirmed(): self
    {
        return $this->state(function (): array {
            $confirmedAt = Date::now()->subDays(3);

            return [
                'status' => InvoiceStatus::CONFIRMED,
                'confirmed_at' => $confirmedAt,
                'sent_at' => null,
                'paid_at' => null,
            ];
        });
    }

    public function sent(): self
    {
        return $this->state(function (): array {
            $confirmedAt = Date::now()->subDays(5);
            $sentAt = $confirmedAt->copy()->addDay();

            return [
                'status' => InvoiceStatus::SENT,
                'confirmed_at' => $confirmedAt,
                'sent_at' => $sentAt,
                'paid_at' => null,
            ];
        });
    }

    public function paid(): self
    {
        return $this->state(function (): array {
            $confirmedAt = Date::now()->subDays(7);
            $sentAt = $confirmedAt->copy()->addDay();
            $paidAt = $sentAt->copy()->addDay();

            return [
                'status' => InvoiceStatus::PAID,
                'confirmed_at' => $confirmedAt,
                'sent_at' => $sentAt,
                'paid_at' => $paidAt,
            ];
        });
    }
}
