<?php

namespace Tests\Unit\Invoicing\Enums;

use App\Enums\InvoiceStatus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('invoicing')]
class InvoiceStatusTest extends TestCase
{
    #[Test]
    public function it_defines_all_expected_values(): void
    {
        $this->assertEqualsCanonicalizing(
            [
                InvoiceStatus::DRAFT,
                InvoiceStatus::CONFIRMED,
                InvoiceStatus::SENT,
                InvoiceStatus::PAID,
                InvoiceStatus::OVERDUE,
                InvoiceStatus::CANCELLED,
            ],
            InvoiceStatus::getValues(),
        );
    }

    #[Test]
    public function it_returns_expected_french_descriptions(): void
    {
        $this->assertSame('Brouillon', InvoiceStatus::getDescription(InvoiceStatus::DRAFT));
        $this->assertSame('Confirmée', InvoiceStatus::getDescription(InvoiceStatus::CONFIRMED));
        $this->assertSame('Envoyée', InvoiceStatus::getDescription(InvoiceStatus::SENT));
        $this->assertSame('Payée', InvoiceStatus::getDescription(InvoiceStatus::PAID));
        $this->assertSame('En retard', InvoiceStatus::getDescription(InvoiceStatus::OVERDUE));
        $this->assertSame('Annulée', InvoiceStatus::getDescription(InvoiceStatus::CANCELLED));
    }
}
