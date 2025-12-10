<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Models;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Translatable\HasTranslations;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceItemTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_uses_expected_traits_and_casts(): void
    {
        $traits = class_uses_recursive(InvoiceItem::class);

        $this->assertContains(HasUuids::class, $traits);
        $this->assertContains(HasFactory::class, $traits);
        $this->assertContains(HasTranslations::class, $traits);

        $item = InvoiceItem::factory()->make([
            'label' => ['fr' => 'Label FR', 'en' => 'Label EN'],
            'description' => ['fr' => 'Description FR'],
            'unit_price_htva' => 500,
            'quantity' => 2,
            'subtotal_htva' => 1000,
            'vat_amount' => 210,
            'total_ttc' => 1210,
            'metadata' => ['source' => 'factory'],
        ]);

        $casts = $item->getCasts();
        $this->assertSame('array', $casts['label']);
        $this->assertSame('array', $casts['description']);
        $this->assertSame('int', $casts['unit_price_htva']);
        $this->assertSame('int', $casts['quantity']);
        $this->assertSame('int', $casts['subtotal_htva']);
        $this->assertSame('int', $casts['vat_amount']);
        $this->assertSame('int', $casts['total_ttc']);
        $this->assertSame('array', $casts['metadata']);

        $this->assertIsArray($item->label);
        $this->assertSame('Label FR', $item->label['fr']);
        $this->assertSame('Label EN', $item->label['en']);
    }

    #[Test]
    public function it_belongs_to_invoice(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $item->invoice());
        $this->assertTrue($item->invoice->is($invoice));
    }

    #[Test]
    public function it_optionally_belongs_to_module(): void
    {
        $module = Module::factory()->create();
        $item = InvoiceItem::factory()->create([
            'module_id' => $module->id,
        ]);

        $this->assertInstanceOf(BelongsTo::class, $item->module());
        $this->assertTrue($item->module->is($module));
    }
}
