<?php

namespace Tests\Unit\Invoicing\Enums;

use App\Enums\InvoiceIssuerType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('invoicing')]
class InvoiceIssuerTypeTest extends TestCase
{
    #[Test]
    public function it_defines_all_expected_values(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                InvoiceIssuerType::HEXEKO,
                InvoiceIssuerType::DIVISION,
            ],
            InvoiceIssuerType::getValues(),
        );
    }

    #[Test]
    public function it_returns_expected_french_descriptions(): void
    {
        $this->assertSame('Hexeko', InvoiceIssuerType::getDescription(InvoiceIssuerType::HEXEKO));
        $this->assertSame('Division', InvoiceIssuerType::getDescription(InvoiceIssuerType::DIVISION));
    }
}
