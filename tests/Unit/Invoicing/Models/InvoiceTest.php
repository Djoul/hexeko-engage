<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Models;

use App\Models\Division;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_uses_expected_traits_and_casts(): void
    {
        $traits = class_uses_recursive(Invoice::class);

        $this->assertContains(HasUuids::class, $traits);
        $this->assertContains(HasFactory::class, $traits);
        $this->assertContains(SoftDeletes::class, $traits);

        $invoice = Invoice::factory()->make([
            'subtotal_htva' => 1234,
            'vat_amount' => 246,
            'total_ttc' => 1480,
            'metadata' => ['source' => 'factory'],
            'billing_period_start' => '2025-01-01',
            'billing_period_end' => '2025-01-31',
        ]);

        $casts = $invoice->getCasts();

        $this->assertSame('int', $casts['subtotal_htva']);
        $this->assertSame('int', $casts['vat_amount']);
        $this->assertSame('int', $casts['total_ttc']);
        $this->assertSame('array', $casts['metadata']);
        $this->assertSame('date', $casts['billing_period_start']);
        $this->assertSame('date', $casts['billing_period_end']);

        $this->assertInstanceOf(Auditable::class, $invoice);
        $this->assertSame(1234, $invoice->subtotal_htva);
        $this->assertSame(['source' => 'factory'], $invoice->metadata);
        $this->assertEquals('2025-01-01', $invoice->billing_period_start->toDateString());
        $this->assertEquals('2025-01-31', $invoice->billing_period_end->toDateString());
    }

    #[Test]
    public function it_defines_items_relation(): void
    {
        $invoice = Invoice::factory()->create();
        $items = InvoiceItem::factory()->count(3)->create([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertInstanceOf(HasMany::class, $invoice->items());
        $this->assertCount(3, $invoice->items);
        $this->assertEqualsCanonicalizing(
            $items->pluck('id')->all(),
            $invoice->items->pluck('id')->all()
        );
    }

    #[Test]
    public function it_morphs_recipient_relation(): void
    {
        $division = Division::factory()->create();

        $invoice = Invoice::factory()->create([
            'recipient_type' => Division::class,
            'recipient_id' => $division->id,
        ]);

        $this->assertInstanceOf(MorphTo::class, $invoice->recipient());
        $this->assertTrue($invoice->recipient->is($division));
    }

    #[Test]
    public function it_supports_soft_deletes(): void
    {
        $invoice = Invoice::factory()->create();

        $invoice->delete();

        $this->assertSoftDeleted($invoice); // helper assertion
        $this->assertNotNull($invoice->fresh()->deleted_at);
    }

    #[Test]
    public function it_scopes_items_with_factory_state(): void
    {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(2), 'items')
            ->create();

        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->items->every(fn (InvoiceItem $item): bool => $item->invoice_id === $invoice->id));
    }
}
