<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\CalculateInvoiceItemsAction;
use App\DTOs\Invoicing\CreateInvoiceDTO;
use App\DTOs\Invoicing\CreateInvoiceItemDTO;
use App\DTOs\Invoicing\InvoiceItemDTO;
use App\DTOs\Invoicing\ProrataCalculationDTO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class CalculateInvoiceItemsActionTest extends TestCase
{
    private CalculateInvoiceItemsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(CalculateInvoiceItemsAction::class);
    }

    #[Test]
    public function it_builds_invoice_item_dtos_with_computed_amounts(): void
    {
        $dto = new CreateInvoiceDTO(
            recipientType: 'division',
            recipientId: 'division-uuid',
            billingPeriodStart: '2025-05-01',
            billingPeriodEnd: '2025-05-31',
            items: [
                new CreateInvoiceItemDTO(
                    itemType: 'core_package',
                    moduleId: null,
                    unitPriceHtva: 4_000,
                    quantity: 10,
                    prorataPercentage: 1.0,
                    prorata: new ProrataCalculationDTO(1.0, 31, 31, '2025-05-01', '2025-05-31'),
                ),
                new CreateInvoiceItemDTO(
                    itemType: 'module',
                    moduleId: 'module-123',
                    unitPriceHtva: 2_000,
                    quantity: 5,
                    prorataPercentage: 0.5,
                    prorata: null,
                ),
            ],
        );

        $items = $this->action->execute($dto, 'FR');

        $this->assertCount(2, $items);
        $this->assertContainsOnlyInstancesOf(InvoiceItemDTO::class, $items);

        $corePackage = $items[0];
        $this->assertSame('core_package', $corePackage->itemType);
        $this->assertSame(40_000, $corePackage->amounts->subtotalHtva);
        $this->assertSame(8_000, $corePackage->amounts->vatAmount);
        $this->assertSame(48_000, $corePackage->amounts->totalTtc);
        $this->assertSame(1.0, $corePackage->prorata?->percentage);

        $moduleItem = $items[1];
        $this->assertSame('module', $moduleItem->itemType);
        $this->assertSame('module-123', $moduleItem->moduleId);
        $this->assertSame(5_000, $moduleItem->amounts->subtotalHtva);
        $this->assertSame(1_000, $moduleItem->amounts->vatAmount);
        $this->assertSame(6_000, $moduleItem->amounts->totalTtc);
        $this->assertNull($moduleItem->prorata);
    }
}
