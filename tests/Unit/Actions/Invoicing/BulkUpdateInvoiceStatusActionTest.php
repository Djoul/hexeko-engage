<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\BulkUpdateInvoiceStatusAction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class BulkUpdateInvoiceStatusActionTest extends TestCase
{
    use DatabaseTransactions;

    private BulkUpdateInvoiceStatusAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(BulkUpdateInvoiceStatusAction::class);
    }

    #[Test]
    public function it_updates_multiple_invoices(): void
    {
        $invoiceA = Invoice::factory()->create(['status' => InvoiceStatus::DRAFT]);
        $invoiceB = Invoice::factory()->create(['status' => InvoiceStatus::DRAFT]);

        $result = $this->action->execute([
            'invoice_ids' => [$invoiceA->id, $invoiceB->id],
            'status' => InvoiceStatus::CONFIRMED,
        ]);

        $this->assertSame(2, $result['updated']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceA->id,
            'status' => InvoiceStatus::CONFIRMED,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceB->id,
            'status' => InvoiceStatus::CONFIRMED,
        ]);
    }

    #[Test]
    public function it_reports_errors_for_invalid_ids(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::DRAFT]);

        $result = $this->action->execute([
            'invoice_ids' => [$invoice->id, Str::uuid()->toString()],
            'status' => InvoiceStatus::CONFIRMED,
        ]);

        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['failed']);
        $this->assertNotEmpty($result['errors']);
    }
}
