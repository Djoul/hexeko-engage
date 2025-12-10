<?php

namespace Tests\Unit\Invoicing\Enums;

use App\Enums\InvoiceRecipientType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('invoicing')]
class InvoiceRecipientTypeTest extends TestCase
{
    #[Test]
    public function it_defines_all_expected_values(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                InvoiceRecipientType::DIVISION,
                InvoiceRecipientType::FINANCER,
            ],
            InvoiceRecipientType::getValues(),
        );
    }

    #[Test]
    public function it_returns_expected_french_descriptions(): void
    {
        $this->assertSame('Division', InvoiceRecipientType::getDescription(InvoiceRecipientType::DIVISION));
        $this->assertSame('Financeur', InvoiceRecipientType::getDescription(InvoiceRecipientType::FINANCER));
    }
}
