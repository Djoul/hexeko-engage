<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Invoicing;

use App\Http\Resources\Invoicing\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('resources')]
class InvoiceResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_invoice_with_items(): void
    {
        $invoice = Invoice::factory()
            ->hasItems(2)
            ->create();

        $resource = new InvoiceResource($invoice->fresh(['items']));

        $data = $resource->toArray(Request::create('/test'));

        $this->assertSame($invoice->id, $data['id']);
        $this->assertSame($invoice->invoice_number, $data['invoice_number']);
        $this->assertSame($invoice->status, $data['status']);
        $this->assertArrayHasKey('recipient', $data);
        $this->assertArrayHasKey('amounts', $data);
        $this->assertCount(2, $data['items']);
    }
}
