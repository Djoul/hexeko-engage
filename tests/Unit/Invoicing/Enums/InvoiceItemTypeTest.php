<?php

namespace Tests\Unit\Invoicing\Enums;

use App\Enums\InvoiceItemType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('invoicing')]
class InvoiceItemTypeTest extends TestCase
{
    #[Test]
    public function it_defines_all_expected_values(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                InvoiceItemType::CORE_PACKAGE,
                InvoiceItemType::MODULE,
                InvoiceItemType::ADJUSTMENT,
                InvoiceItemType::OTHER,
            ],
            InvoiceItemType::getValues(),
        );
    }

    #[Test]
    public function it_returns_expected_french_descriptions(): void
    {
        $this->assertSame('Offre cœur', InvoiceItemType::getDescription(InvoiceItemType::CORE_PACKAGE));
        $this->assertSame('Module', InvoiceItemType::getDescription(InvoiceItemType::MODULE));
        $this->assertSame('Ajustement', InvoiceItemType::getDescription(InvoiceItemType::ADJUSTMENT));
        $this->assertSame('Autre', InvoiceItemType::getDescription(InvoiceItemType::OTHER));
    }
}
