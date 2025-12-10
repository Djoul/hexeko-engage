<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\DTOs;

use App\DTOs\Invoicing\CreateInvoiceDTO;
use App\DTOs\Invoicing\CreateInvoiceItemDTO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class CreateInvoiceDtoTest extends TestCase
{
    #[Test]
    public function it_holds_typed_properties_for_invoice_creation(): void
    {
        $itemDto = new CreateInvoiceItemDTO(
            itemType: 'module',
            moduleId: 'module-123',
            unitPriceHtva: 1500,
            quantity: 2,
            prorataPercentage: 1.0,
        );

        $dto = new CreateInvoiceDTO(
            recipientType: 'financer',
            recipientId: 'finacer-uuid',
            billingPeriodStart: '2025-01-01',
            billingPeriodEnd: '2025-01-31',
            items: [$itemDto],
        );

        $this->assertSame('financer', $dto->recipientType);
        $this->assertSame('finacer-uuid', $dto->recipientId);
        $this->assertCount(1, $dto->items);
        $this->assertSame(1500, $dto->items[0]->unitPriceHtva);
    }

    #[Test]
    public function it_exports_to_array_structure(): void
    {
        $dto = new CreateInvoiceDTO(
            recipientType: 'division',
            recipientId: 'division-uuid',
            billingPeriodStart: '2025-02-01',
            billingPeriodEnd: '2025-02-28',
            items: [
                new CreateInvoiceItemDTO(
                    itemType: 'core_package',
                    moduleId: null,
                    unitPriceHtva: 2000,
                    quantity: 1,
                    prorataPercentage: 0.5,
                ),
            ],
        );

        $payload = $dto->toArray();

        $this->assertSame('division', $payload['recipient_type']);
        $this->assertSame('division-uuid', $payload['recipient_id']);
        $this->assertSame('2025-02-01', $payload['billing_period_start']);
        $this->assertCount(1, $payload['items']);
        $this->assertSame('core_package', $payload['items'][0]['item_type']);
        $this->assertSame(0.5, $payload['items'][0]['prorata_percentage']);
    }
}
