<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Factories;

use App\Enums\InvoiceItemType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceItemFactoryTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_translated_labels_and_consistent_amounts(): void
    {
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->for($invoice)->create();

        $this->assertGreaterThanOrEqual(0, $item->subtotal_htva);
        $this->assertGreaterThanOrEqual(0, $item->total_ttc ?? 0);
        $this->assertSame($item->subtotal_htva, $item->unit_price_htva * $item->quantity);
        $this->assertIsArray($item->label);
        $this->assertArrayHasKey('fr', $item->label);
        $this->assertIsArray($item->description ?? []);
    }

    #[Test]
    public function it_can_generate_core_package_state(): void
    {
        $item = InvoiceItem::factory()->corePackage()->create();

        $this->assertSame(InvoiceItemType::CORE_PACKAGE, $item->item_type);
    }
}
