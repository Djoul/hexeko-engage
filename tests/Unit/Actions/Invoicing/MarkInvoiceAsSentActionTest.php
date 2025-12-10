<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\MarkInvoiceAsSentAction;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class MarkInvoiceAsSentActionTest extends TestCase
{
    use DatabaseTransactions;

    private MarkInvoiceAsSentAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(MarkInvoiceAsSentAction::class);
    }

    #[Test]
    public function it_marks_invoice_as_sent(): void
    {
        Carbon::setTestNow('2025-05-03 09:00:00');

        $invoice = Invoice::factory()->confirmed()->create([
            'sent_at' => null,
        ]);

        $result = $this->action->execute($invoice->fresh());

        $this->assertInstanceOf(InvoiceDTO::class, $result);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::SENT,
        ]);
        $this->assertTrue(Carbon::parse($invoice->fresh()->sent_at)->equalTo(Carbon::now()));

        Carbon::setTestNow();
    }

    #[Test]
    public function it_requires_confirmed_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::DRAFT,
        ]);

        $this->expectException(LogicException::class);

        $this->action->execute($invoice);
    }
}
