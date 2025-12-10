<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoicing;

use App\DTOs\Invoicing\CreateInvoiceItemDTO;
use App\DTOs\Invoicing\InvoiceAmountsDTO;
use App\Services\Invoicing\CalculateInvoiceService;
use App\Services\Invoicing\VatCalculatorService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class CalculateInvoiceServiceTest extends TestCase
{
    private CalculateInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CalculateInvoiceService::class);
    }

    #[Test]
    public function it_calculates_item_amounts_with_prorata_and_vat(): void
    {
        $item = new CreateInvoiceItemDTO(
            itemType: 'module',
            moduleId: 'module-1',
            unitPriceHtva: 5000,
            quantity: 10,
            prorataPercentage: 0.5,
        );

        $result = $this->service->calculateItemAmounts($item, 'FR');

        $this->assertInstanceOf(InvoiceAmountsDTO::class, $result);
        $this->assertSame(25000, $result->subtotalHtva);
        $this->assertSame(5000, $result->vatAmount);
        $this->assertSame(30000, $result->totalTtc);
        $this->assertSame('EUR', $result->currency);
    }

    #[Test]
    public function it_aggregates_multiple_items_into_invoice_amounts(): void
    {
        $items = [
            new CreateInvoiceItemDTO('core_package', null, 4000, 10, 1.0),
            new CreateInvoiceItemDTO('module', 'module-2', 2000, 5, 0.5),
        ];

        $result = $this->service->calculateInvoiceAmounts($items, 'BE');

        $this->assertSame(45000, $result->subtotalHtva);
        $this->assertSame(9450, $result->vatAmount);
        $this->assertSame(54450, $result->totalTtc);
        $this->assertSame('EUR', $result->currency);
    }

    #[Test]
    public function it_uses_vat_calculator_service_for_amounts(): void
    {
        $vatCalculator = $this->mock(VatCalculatorService::class);
        $vatCalculator->shouldReceive('calculateVat')
            ->once()
            ->with(20000, 'PT')
            ->andReturn(4600);
        $vatCalculator->shouldReceive('calculateTotalTtc')
            ->once()
            ->with(20000, 4600)
            ->andReturn(24600);

        $service = new CalculateInvoiceService($vatCalculator);

        $item = new CreateInvoiceItemDTO('core_package', null, 2000, 10, 1.0);
        $result = $service->calculateInvoiceAmounts([$item], 'PT');

        $this->assertSame(20000, $result->subtotalHtva);
        $this->assertSame(4600, $result->vatAmount);
        $this->assertSame(24600, $result->totalTtc);
    }

    #[Test]
    public function it_uses_fallback_vat_for_unsupported_countries(): void
    {
        $result = $this->service->calculateInvoiceAmounts([
            new CreateInvoiceItemDTO('core_package', null, 1000, 1, 1.0),
        ], 'DE');

        $this->assertSame(1000, $result->subtotalHtva);
        $this->assertSame(200, $result->vatAmount, 'Fallback VAT should be 20%');
        $this->assertSame(1200, $result->totalTtc);
    }
}
