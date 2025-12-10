<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Builders;

use App\DTOs\Invoicing\CreateInvoiceDTO;
use App\Services\Invoicing\InvoiceBuilder;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_invoice_dto_for_division_with_items(): void
    {
        $builder = new InvoiceBuilder;

        $dto = $builder
            ->forDivision('division-uuid')
            ->forPeriod(Carbon::parse('2025-02-01'), Carbon::parse('2025-02-28'))
            ->addCorePackageItem(4000, 12, 1.0)
            ->addModuleItem('module-uuid', 2500, 8, 0.5)
            ->build();

        $this->assertInstanceOf(CreateInvoiceDTO::class, $dto);

        $asArray = $dto->toArray();

        $this->assertSame('division', $asArray['recipient_type']);
        $this->assertSame('division-uuid', $asArray['recipient_id']);
        $this->assertSame('2025-02-01', $asArray['billing_period_start']);
        $this->assertSame('2025-02-28', $asArray['billing_period_end']);
        $this->assertCount(2, $asArray['items']);
        $this->assertSame(4000, $asArray['items'][0]['unit_price_htva']);
        $this->assertSame(2500, $asArray['items'][1]['unit_price_htva']);
        $this->assertSame(0.5, $asArray['items'][1]['prorata_percentage']);
    }

    #[Test]
    public function it_builds_invoice_dto_for_financer(): void
    {
        $builder = new InvoiceBuilder;

        $dto = $builder
            ->forFinancer('financer-uuid')
            ->forPeriod(Carbon::parse('2025-03-01'), Carbon::parse('2025-03-31'))
            ->addCorePackageItem(3000, 5, 0.75)
            ->build();

        $asArray = $dto->toArray();

        $this->assertSame('financer', $asArray['recipient_type']);
        $this->assertSame('financer-uuid', $asArray['recipient_id']);
        $this->assertSame('2025-03-01', $asArray['billing_period_start']);
        $this->assertSame('2025-03-31', $asArray['billing_period_end']);
        $this->assertSame(0.75, $asArray['items'][0]['prorata_percentage']);
    }
}
