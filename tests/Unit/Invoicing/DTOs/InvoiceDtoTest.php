<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\DTOs;

use App\DTOs\Invoicing\InvoiceDTO;
use App\DTOs\Invoicing\InvoiceItemDTO;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceDtoTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_builds_dto_from_invoice_model(): void
    {
        $financer = Financer::factory()->create();

        $invoice = Invoice::factory()
            ->sent()
            ->for($financer, 'recipient')
            ->has(InvoiceItem::factory()->count(2), 'items')
            ->create();

        $invoice->load('items');

        $dto = InvoiceDTO::fromModel($invoice);

        $this->assertSame($invoice->id, $dto->id);
        $this->assertSame($invoice->invoice_number, $dto->invoiceNumber);
        $this->assertSame($invoice->status, $dto->status);
        $this->assertCount(2, $dto->items);
        $this->assertInstanceOf(InvoiceItemDTO::class, $dto->items[0]);
        $this->assertEquals($invoice->subtotal_htva, $dto->amounts->subtotalHtva);
        $this->assertEquals($invoice->total_ttc, $dto->amounts->totalTtc);
    }

    #[Test]
    public function it_serializes_invoice_dto_to_array(): void
    {
        $invoice = Invoice::factory()
            ->paid()
            ->has(InvoiceItem::factory()->count(1), 'items')
            ->create();

        $dto = InvoiceDTO::fromModel($invoice->load('items'));
        $payload = $dto->toArray();

        $this->assertSame($invoice->id, $payload['id']);
        $this->assertSame($invoice->invoice_number, $payload['invoice_number']);
        $this->assertArrayHasKey('recipient', $payload);
        $this->assertSame($invoice->recipient_type, $payload['recipient']['type']);
        $this->assertSame($invoice->recipient_id, $payload['recipient']['id']);
        $this->assertSame($invoice->subtotal_htva, $payload['amounts']['subtotal_htva']);
        $this->assertSame($invoice->total_ttc, $payload['amounts']['total_ttc']);
        $this->assertArrayHasKey('billing_period_start', $payload['dates']);
        $this->assertCount(1, $payload['items']);
    }
}
