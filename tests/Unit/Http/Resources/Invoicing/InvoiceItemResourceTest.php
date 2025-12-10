<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Invoicing;

use App\Http\Resources\Invoicing\InvoiceItemResource;
use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('resources')]
class InvoiceItemResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_invoice_item(): void
    {
        $item = InvoiceItem::factory()->create([
            'metadata' => ['key' => 'value'],
        ]);

        $resource = new InvoiceItemResource($item);
        $data = $resource->toArray(Request::create('/test'));

        $this->assertSame($item->id, $data['id']);
        $this->assertSame($item->item_type, $data['item_type']);
        $this->assertSame($item->unit_price_htva, $data['amounts']['unit_price_htva']);
        $this->assertSame($item->quantity, $data['quantity']);
    }
}
