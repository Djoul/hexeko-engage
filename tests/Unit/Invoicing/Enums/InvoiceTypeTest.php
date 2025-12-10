<?php

namespace Tests\Unit\Invoicing\Enums;

use App\Enums\InvoiceType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('invoicing')]
class InvoiceTypeTest extends TestCase
{
    #[Test]
    public function it_defines_all_expected_values(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                InvoiceType::HEXEKO_TO_DIVISION,
                InvoiceType::DIVISION_TO_FINANCER,
            ],
            InvoiceType::getValues(),
        );
    }

    #[Test]
    public function it_returns_expected_french_descriptions(): void
    {
        $this->assertSame('Hexeko vers division', InvoiceType::getDescription(InvoiceType::HEXEKO_TO_DIVISION));
        $this->assertSame('Division vers financeur', InvoiceType::getDescription(InvoiceType::DIVISION_TO_FINANCER));
    }
}
