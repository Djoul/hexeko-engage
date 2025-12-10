<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceFactoryTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_generates_unique_invoice_numbers_and_valid_amounts(): void
    {
        $invoiceA = Invoice::factory()->create();
        $invoiceB = Invoice::factory()->create();

        $this->assertNotSame($invoiceA->invoice_number, $invoiceB->invoice_number);
        $this->assertTrue(Str::startsWith($invoiceA->invoice_number, 'INV-'));

        $this->assertGreaterThanOrEqual(0, $invoiceA->subtotal_htva);
        $this->assertGreaterThanOrEqual(0, $invoiceA->total_ttc);
        $this->assertGreaterThanOrEqual($invoiceA->subtotal_htva, $invoiceA->total_ttc);
    }

    #[Test]
    public function it_sets_chronological_status_dates(): void
    {
        $invoice = Invoice::factory()->sent()->create();

        $this->assertNotNull($invoice->confirmed_at);
        $this->assertNotNull($invoice->sent_at);
        $this->assertTrue($invoice->confirmed_at->lessThanOrEqualTo($invoice->sent_at));

        $paidInvoice = Invoice::factory()->paid()->create();

        $this->assertNotNull($paidInvoice->confirmed_at);
        $this->assertNotNull($paidInvoice->sent_at);
        $this->assertNotNull($paidInvoice->paid_at);
        $this->assertTrue($paidInvoice->confirmed_at->lessThanOrEqualTo($paidInvoice->sent_at));
        $this->assertTrue($paidInvoice->sent_at->lessThanOrEqualTo($paidInvoice->paid_at));
        $this->assertSame(InvoiceStatus::PAID, $paidInvoice->status);
    }
}
