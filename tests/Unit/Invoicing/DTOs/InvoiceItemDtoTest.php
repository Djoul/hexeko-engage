<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\DTOs;

use App\DTOs\Invoicing\InvoiceAmountsDTO;
use App\DTOs\Invoicing\InvoiceItemDTO;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceItemDtoTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_converts_invoice_item_model_to_dto(): void
    {
        $item = InvoiceItem::factory()->create();

        $dto = InvoiceItemDTO::fromModel($item);

        $this->assertSame($item->id, $dto->id);
        $this->assertSame($item->item_type, $dto->itemType);
        $this->assertSame($item->module_id, $dto->moduleId);
        $this->assertSame($item->quantity, $dto->quantity);
        $this->assertInstanceOf(InvoiceAmountsDTO::class, $dto->amounts);
        $this->assertEquals($item->subtotal_htva, $dto->amounts->subtotalHtva);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $item = InvoiceItem::factory()->create();
        $payload = InvoiceItemDTO::fromModel($item)->toArray();

        $this->assertSame($item->id, $payload['id']);
        $this->assertSame($item->item_type, $payload['item_type']);
        $this->assertSame($item->getTranslations('label'), $payload['label']);
        $this->assertSame($item->metadata, $payload['metadata']);
        $this->assertArrayHasKey('amounts', $payload);
        $this->assertSame($item->subtotal_htva, $payload['amounts']['subtotal_htva']);
    }
}
